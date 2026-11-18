/**
 * Sincronización de las marcaciones guardadas sin conexión (SC-53 / HU-11.3).
 *
 * Envía la cola local por lotes a POST .../marks/sync y saca de la cola cada
 * marcación que el servidor contestó, sea cual sea el resultado: el servidor
 * ya decidió y reenviarla no cambiaría nada (ver ADR 0001). Las que no se
 * registraron pasan a la lista de rechazadas para que el docente las revise
 * (SC-56).
 */
import { firstErrorMessage, postJson, type RequestOptions } from '@/lib/http';
import type { MarkResult } from '@/types/attendance';
import {
    countPendingMarks,
    listPendingMarks,
    moveToRejected,
    removeMark,
    type RejectedMark,
} from './markQueue';

export type SyncedMarkResult = MarkResult & { client_uuid: string };

export interface SyncBatchSummary {
    /** Marcaciones que el servidor contestó en este lote. */
    results: SyncedMarkResult[];
    /** Marcaciones que siguen en la cola de la sesión. */
    remaining: number;
    /** Las que el servidor no registró; quedan para revisión del docente. */
    rejected: RejectedMark[];
}

/** Resultados que significan que la marcación no quedó registrada. */
export const REJECTED_RESULTS: ReadonlySet<MarkResult['result']> = new Set([
    'no_inscrito',
    'sin_ingreso',
    'dni_invalido',
    'hora_invalida',
]);

/**
 * El servidor respondió, pero rechazó todo el lote (acta cerrada, sesión
 * expirada, sin permiso). Reintentar no sirve hasta que alguien lo resuelva.
 */
export class SyncRejectedError extends Error {
    constructor(
        public readonly status: number,
        message: string,
    ) {
        super(message);
        this.name = 'SyncRejectedError';
    }
}

type PostJson = (
    url: string,
    body: unknown,
    options?: RequestOptions,
) => Promise<{ ok: boolean; status: number; data: unknown }>;

export interface SyncOptions {
    batchSize?: number;
    timeoutMs?: number;
    /** Para pruebas. */
    post?: PostJson;
}

export const SYNC_BATCH_SIZE = 50;

/**
 * Envía un lote de la cola de la sesión. Rechaza si no hay red (el error de
 * `fetch` o `NetworkTimeoutError`) o con `SyncRejectedError` si el servidor
 * rechazó el lote completo.
 */
export async function syncPendingBatch(
    courseId: number,
    session: number,
    options: SyncOptions = {},
): Promise<SyncBatchSummary> {
    const {
        batchSize = SYNC_BATCH_SIZE,
        timeoutMs = 15000,
        post = postJson,
    } = options;

    const batch = (await listPendingMarks(courseId, session)).slice(
        0,
        batchSize,
    );

    if (batch.length === 0) {
        return { results: [], remaining: 0, rejected: [] };
    }

    const response = await post(
        `/courses/${courseId}/sessions/${session}/marks/sync`,
        {
            marks: batch.map((mark) => ({
                client_uuid: mark.client_uuid,
                identifier: mark.identifier,
                type: mark.type,
                origin: mark.origin,
                captured_at: mark.captured_at,
            })),
        },
        { timeoutMs },
    );
    const { ok, status } = response;
    const data = response.data as { results?: SyncedMarkResult[] } | null;

    if (!ok || !data || !Array.isArray(data.results)) {
        throw new SyncRejectedError(
            status,
            firstErrorMessage(
                data,
                status === 419
                    ? 'La sesión del kiosco expiró. Recargue la página para enviar las marcaciones guardadas.'
                    : 'El servidor no aceptó las marcaciones guardadas.',
            ),
        );
    }

    const sent = new Map(batch.map((mark) => [mark.client_uuid, mark]));
    const results = data.results.filter((result) =>
        sent.has(result.client_uuid),
    );
    const rejected: RejectedMark[] = [];

    for (const result of results) {
        const mark = sent.get(result.client_uuid);

        if (mark && REJECTED_RESULTS.has(result.result)) {
            rejected.push(
                await moveToRejected(mark, result.result, result.message),
            );
        } else {
            await removeMark(result.client_uuid);
        }
    }

    return {
        results,
        remaining: await countPendingMarks(courseId, session),
        rejected,
    };
}

export type SyncStep = 'done' | 'more' | 'retry' | 'stop';

/** Esperas entre reintentos cuando no hay red: 5 s, 10 s, 20 s… hasta 2 min. */
export const RETRY_DELAYS_MS = [5000, 10000, 20000, 40000, 80000, 120000];

export interface SyncLoop {
    /** Intentar ahora (al abrir el kiosco, al volver la red, etc.). */
    trigger: () => Promise<void>;
    /**
     * Intentar solo si no hay una sincronización en curso ni un reintento
     * programado; no reinicia la espera (p. ej. tras guardar sin conexión).
     */
    kick: () => Promise<void>;
    stop: () => void;
    /** Reintentos seguidos sin red. */
    readonly attempts: number;
}

/**
 * Ejecuta `step` hasta vaciar la cola: si devuelve `more` sigue con el
 * siguiente lote, si devuelve `retry` lo reintenta con espera creciente y si
 * devuelve `done` o `stop` espera al próximo `trigger()`.
 */
export function createSyncLoop(
    step: () => Promise<SyncStep>,
    delays: readonly number[] = RETRY_DELAYS_MS,
): SyncLoop {
    let running: Promise<void> | null = null;
    let rerun = false;
    let stopped = false;
    let attempts = 0;
    let timer: ReturnType<typeof setTimeout> | null = null;

    function clearTimer(): void {
        if (timer) clearTimeout(timer);
        timer = null;
    }

    async function loop(): Promise<void> {
        let outcome: SyncStep;
        do {
            try {
                outcome = await step();
            } catch {
                // Un error inesperado no debe dejar la cola sin reintentos.
                outcome = 'retry';
            }
        } while (outcome === 'more' && !stopped);

        if (outcome === 'retry' && !stopped) {
            const delay = delays[Math.min(attempts, delays.length - 1)];
            attempts++;
            timer = setTimeout(() => {
                timer = null;
                void run();
            }, delay);
        } else {
            attempts = 0;
        }
    }

    function run(): Promise<void> {
        if (stopped) return Promise.resolve();
        if (running) {
            rerun = true;
            return running;
        }

        clearTimer();
        running = loop().finally(() => {
            running = null;
            if (rerun && !stopped) {
                rerun = false;
                void run();
            }
        });

        return running;
    }

    return {
        trigger: () => {
            attempts = 0;
            return run();
        },
        kick: () => {
            if (running) return running;
            if (timer) return Promise.resolve();
            return run();
        },
        stop: () => {
            stopped = true;
            clearTimer();
        },
        get attempts() {
            return attempts;
        },
    };
}
