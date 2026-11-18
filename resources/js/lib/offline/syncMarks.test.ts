import 'fake-indexeddb/auto';
import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    OFFLINE_DB_NAME,
    closeOfflineDatabase,
    countPendingMarks,
    enqueueMark,
} from './markQueue';
import {
    REJECTED_RESULTS,
    SyncRejectedError,
    createSyncLoop,
    syncPendingBatch,
    type SyncStep,
    type SyncedMarkResult,
} from './syncMarks';

async function resetDatabase(): Promise<void> {
    await closeOfflineDatabase();
    await new Promise<void>((resolve, reject) => {
        const request = indexedDB.deleteDatabase(OFFLINE_DB_NAME);
        request.onsuccess = () => resolve();
        request.onerror = () => reject(request.error);
    });
}

async function queue(identifier: string, capturedAt: string, session = 2) {
    return enqueueMark({
        course_id: 7,
        session,
        identifier,
        type: 'entrada',
        origin: 'dni_lector',
        captured_at: capturedAt,
    });
}

function answer(clientUuid: string, result: SyncedMarkResult['result']) {
    return {
        client_uuid: clientUuid,
        result,
        message: result,
        first_mark: false,
        participant: null,
        marked_at: null,
        permanence: null,
    } satisfies SyncedMarkResult;
}

/** Simula el servidor: contesta cada marcación del lote con `result`. */
function serverAnswering(result: SyncedMarkResult['result']) {
    return vi.fn(async (_url: string, body: unknown, _options?: unknown) => {
        const { marks } = body as { marks: { client_uuid: string }[] };

        return {
            ok: true,
            status: 200,
            data: { results: marks.map((m) => answer(m.client_uuid, result)) },
        };
    });
}

describe('syncPendingBatch', () => {
    afterEach(resetDatabase);

    it('no llama al servidor si la cola está vacía', async () => {
        const post = serverAnswering('ingreso_registrado');

        await expect(syncPendingBatch(7, 2, { post })).resolves.toEqual({
            results: [],
            remaining: 0,
        });
        expect(post).not.toHaveBeenCalled();
    });

    it('envía la cola de la sesión en orden y la vacía', async () => {
        const second = await queue('22222222', '2026-11-17T14:05:00.000Z');
        const first = await queue('11111111', '2026-11-17T14:00:00.000Z');
        await queue('33333333', '2026-11-17T14:00:00.000Z', 3);
        const post = serverAnswering('ingreso_registrado');

        const summary = await syncPendingBatch(7, 2, { post });

        expect(post).toHaveBeenCalledOnce();
        const [url, body, options] = post.mock.calls[0];
        expect(url).toBe('/courses/7/sessions/2/marks/sync');
        expect(options).toEqual({ timeoutMs: 15000 });
        expect(body).toEqual({
            marks: [
                {
                    client_uuid: first.client_uuid,
                    identifier: '11111111',
                    type: 'entrada',
                    origin: 'dni_lector',
                    captured_at: '2026-11-17T14:00:00.000Z',
                },
                {
                    client_uuid: second.client_uuid,
                    identifier: '22222222',
                    type: 'entrada',
                    origin: 'dni_lector',
                    captured_at: '2026-11-17T14:05:00.000Z',
                },
            ],
        });
        expect(summary.results).toHaveLength(2);
        expect(summary.remaining).toBe(0);
        // La otra sesión no se toca.
        expect(await countPendingMarks(7, 3)).toBe(1);
    });

    it('también borra las rechazadas: el servidor ya decidió', async () => {
        await queue('87654321', '2026-11-17T14:00:00.000Z');

        const summary = await syncPendingBatch(7, 2, {
            post: serverAnswering('no_inscrito'),
        });

        expect(summary.results[0].result).toBe('no_inscrito');
        expect(REJECTED_RESULTS.has(summary.results[0].result)).toBe(true);
        expect(await countPendingMarks(7, 2)).toBe(0);
    });

    it('envía por lotes y deja el resto en la cola', async () => {
        for (let i = 0; i < 3; i++) {
            await queue(`1000000${i}`, `2026-11-17T14:0${i}:00.000Z`);
        }

        const summary = await syncPendingBatch(7, 2, {
            batchSize: 2,
            post: serverAnswering('ingreso_registrado'),
        });

        expect(summary.results).toHaveLength(2);
        expect(summary.remaining).toBe(1);
    });

    it('conserva la marcación que el servidor no contestó', async () => {
        const kept = await queue('11111111', '2026-11-17T14:00:00.000Z');
        const sent = await queue('22222222', '2026-11-17T14:05:00.000Z');
        const post = vi.fn(async () => ({
            ok: true,
            status: 200,
            data: { results: [answer(sent.client_uuid, 'ingreso_registrado')] },
        }));

        const summary = await syncPendingBatch(7, 2, { post });

        expect(summary.remaining).toBe(1);
        expect(summary.results.map((r) => r.client_uuid)).toEqual([
            sent.client_uuid,
        ]);
        expect(summary.results).not.toContainEqual(
            expect.objectContaining({ client_uuid: kept.client_uuid }),
        );
    });

    it('sin red rechaza y no toca la cola', async () => {
        await queue('11111111', '2026-11-17T14:00:00.000Z');
        const post = vi.fn(async () => {
            throw new TypeError('Failed to fetch');
        });

        await expect(syncPendingBatch(7, 2, { post })).rejects.toThrow(
            'Failed to fetch',
        );
        expect(await countPendingMarks(7, 2)).toBe(1);
    });

    it('si el servidor rechaza el lote (acta cerrada) avisa y no toca la cola', async () => {
        await queue('11111111', '2026-11-17T14:00:00.000Z');
        const post = vi.fn(async () => ({
            ok: false,
            status: 422,
            data: {
                message: 'El acta oficial está cerrada.',
                errors: { course: ['El acta oficial está cerrada.'] },
            },
        }));

        const error = await syncPendingBatch(7, 2, { post }).catch(
            (e: unknown) => e,
        );

        expect(error).toBeInstanceOf(SyncRejectedError);
        expect((error as SyncRejectedError).status).toBe(422);
        expect((error as SyncRejectedError).message).toBe(
            'El acta oficial está cerrada.',
        );
        expect(await countPendingMarks(7, 2)).toBe(1);
    });
});

describe('createSyncLoop', () => {
    afterEach(() => {
        vi.useRealTimers();
    });

    it('sigue con el siguiente lote mientras quede cola', async () => {
        const steps: SyncStep[] = ['more', 'more', 'done'];
        const step = vi.fn(async () => steps.shift() ?? 'done');

        await createSyncLoop(step).trigger();

        expect(step).toHaveBeenCalledTimes(3);
    });

    it('sin red reintenta con espera creciente', async () => {
        vi.useFakeTimers();
        const step = vi.fn(async (): Promise<SyncStep> => 'retry');
        const loop = createSyncLoop(step, [1000, 2000, 4000]);

        await loop.trigger();
        expect(step).toHaveBeenCalledTimes(1);

        await vi.advanceTimersByTimeAsync(999);
        expect(step).toHaveBeenCalledTimes(1);
        await vi.advanceTimersByTimeAsync(1);
        expect(step).toHaveBeenCalledTimes(2);

        await vi.advanceTimersByTimeAsync(2000);
        expect(step).toHaveBeenCalledTimes(3);

        // Después del último valor se queda en la espera máxima.
        await vi.advanceTimersByTimeAsync(4000);
        expect(step).toHaveBeenCalledTimes(4);
        await vi.advanceTimersByTimeAsync(4000);
        expect(step).toHaveBeenCalledTimes(5);

        loop.stop();
    });

    it('al volver la red (trigger) intenta enseguida y reinicia la espera', async () => {
        vi.useFakeTimers();
        const outcomes: SyncStep[] = ['retry', 'retry', 'done'];
        const step = vi.fn(async () => outcomes.shift() ?? 'done');
        const loop = createSyncLoop(step, [1000, 60000]);

        await loop.trigger();
        await vi.advanceTimersByTimeAsync(1000);
        expect(loop.attempts).toBe(2);

        await loop.trigger();
        expect(step).toHaveBeenCalledTimes(3);
        expect(loop.attempts).toBe(0);

        // El reintento pendiente de 60 s se canceló.
        await vi.advanceTimersByTimeAsync(60000);
        expect(step).toHaveBeenCalledTimes(3);
    });

    it('kick no adelanta un reintento ya programado', async () => {
        vi.useFakeTimers();
        const step = vi.fn(async (): Promise<SyncStep> => 'retry');
        const loop = createSyncLoop(step, [1000, 2000]);

        await loop.kick();
        expect(step).toHaveBeenCalledTimes(1);

        // Hay un reintento programado: kick no hace nada ni reinicia la espera.
        await loop.kick();
        expect(step).toHaveBeenCalledTimes(1);
        expect(loop.attempts).toBe(1);

        await vi.advanceTimersByTimeAsync(1000);
        expect(step).toHaveBeenCalledTimes(2);
        loop.stop();
    });

    it('stop deja de reintentar', async () => {
        vi.useFakeTimers();
        const step = vi.fn(async (): Promise<SyncStep> => 'retry');
        const loop = createSyncLoop(step, [1000]);

        await loop.trigger();
        loop.stop();
        await vi.advanceTimersByTimeAsync(5000);

        expect(step).toHaveBeenCalledTimes(1);
    });

    it('no corre dos sincronizaciones a la vez', async () => {
        let release: () => void = () => {};
        const step = vi.fn(
            () =>
                new Promise<SyncStep>((resolve) => {
                    release = () => resolve('done');
                }),
        );
        const loop = createSyncLoop(step);

        const first = loop.trigger();
        const second = loop.trigger();
        expect(step).toHaveBeenCalledTimes(1);

        release();
        await first;
        await second;
        // El segundo pedido se atiende al terminar el primero.
        await vi.waitFor(() => expect(step).toHaveBeenCalledTimes(2));
        release();
    });

    it('un error inesperado se trata como falta de red', async () => {
        vi.useFakeTimers();
        const step = vi
            .fn<() => Promise<SyncStep>>()
            .mockRejectedValueOnce(new Error('IndexedDB bloqueado'))
            .mockResolvedValue('done');
        const loop = createSyncLoop(step, [1000]);

        await loop.trigger();
        expect(loop.attempts).toBe(1);

        await vi.advanceTimersByTimeAsync(1000);
        expect(step).toHaveBeenCalledTimes(2);
        expect(loop.attempts).toBe(0);
    });
});
