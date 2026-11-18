/**
 * Cola local de marcaciones del kiosco para el modo offline (SC-37 / HU-11.1).
 *
 * Cuando el kiosco no llega al servidor, la marcación se guarda en IndexedDB
 * con un identificador único (`client_uuid`) generado al escanear. Ese
 * identificador es el que usará la sincronización (SC-39) para no registrar
 * dos veces la misma marcación.
 */
import { openDB, type DBSchema, type IDBPDatabase } from 'idb';
import type { MarkOrigin, MarkType } from '@/types/attendance';

export interface QueuedMark {
    /** Identificador único de la marcación, generado en el dispositivo. */
    client_uuid: string;
    course_id: number;
    session: number;
    /** DNI tal como lo leyó el lector o lo digitó el operador. */
    identifier: string;
    /** Los 8 dígitos del DNI extraídos de `identifier` (como en el servidor). */
    dni: string | null;
    type: MarkType;
    origin: MarkOrigin;
    /** Hora del dispositivo al escanear (ISO 8601). */
    captured_at: string;
    /** Intentos de sincronización fallidos. */
    attempts: number;
}

export type NewQueuedMark = Pick<
    QueuedMark,
    'course_id' | 'session' | 'identifier' | 'type' | 'origin'
> &
    Partial<Pick<QueuedMark, 'client_uuid' | 'captured_at'>>;

/**
 * Marcación guardada sin conexión que el servidor no registró al sincronizar
 * (SC-56). Queda en el dispositivo hasta que el docente la revise.
 */
export interface RejectedMark {
    client_uuid: string;
    course_id: number;
    session: number;
    identifier: string;
    dni: string | null;
    type: MarkType;
    captured_at: string;
    /** Resultado del servidor: no_inscrito, sin_ingreso, dni_invalido, hora_invalida. */
    result: string;
    /** Motivo que explicó el servidor. */
    message: string;
    rejected_at: string;
}

interface KioskOfflineDB extends DBSchema {
    marks: {
        key: string;
        value: QueuedMark;
        indexes: { by_session: [number, number] };
    };
    rejected: {
        key: string;
        value: RejectedMark;
        indexes: { by_session: [number, number] };
    };
}

export const OFFLINE_DB_NAME = 'sigc-kiosco-offline';
/** v1: cola de marcaciones (SC-46) · v2: rechazadas al sincronizar (SC-56). */
const OFFLINE_DB_VERSION = 2;

let dbPromise: Promise<IDBPDatabase<KioskOfflineDB>> | null = null;

function database(): Promise<IDBPDatabase<KioskOfflineDB>> {
    dbPromise ??= openDB<KioskOfflineDB>(OFFLINE_DB_NAME, OFFLINE_DB_VERSION, {
        upgrade(db, oldVersion) {
            if (oldVersion < 1) {
                const marks = db.createObjectStore('marks', {
                    keyPath: 'client_uuid',
                });
                marks.createIndex('by_session', ['course_id', 'session']);
            }

            if (oldVersion < 2) {
                const rejected = db.createObjectStore('rejected', {
                    keyPath: 'client_uuid',
                });
                rejected.createIndex('by_session', ['course_id', 'session']);
            }
        },
    });

    return dbPromise;
}

/**
 * Igual que AttendancePermanenceService::extractDni(): el primer grupo de 8
 * dígitos de la lectura (el lector puede enviar más caracteres).
 */
export function extractDni(raw: string): string | null {
    return /(\d{8})/.exec(raw)?.[1] ?? null;
}

export function isOfflineStorageAvailable(): boolean {
    return typeof indexedDB !== 'undefined';
}

/**
 * UUID v4. `crypto.randomUUID()` solo existe en contextos seguros (HTTPS o
 * localhost); si el kiosco se abre por HTTP en la red local se arma con
 * `crypto.getRandomValues()`, que sí está disponible.
 */
export function generateClientUuid(): string {
    if (typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = Array.from(bytes, (b) => b.toString(16).padStart(2, '0'));

    return [
        hex.slice(0, 4).join(''),
        hex.slice(4, 6).join(''),
        hex.slice(6, 8).join(''),
        hex.slice(8, 10).join(''),
        hex.slice(10, 16).join(''),
    ].join('-');
}

/** Guarda una marcación en el dispositivo y la devuelve tal como quedó. */
export async function enqueueMark(input: NewQueuedMark): Promise<QueuedMark> {
    const mark: QueuedMark = {
        client_uuid: input.client_uuid ?? generateClientUuid(),
        course_id: input.course_id,
        session: input.session,
        identifier: input.identifier.trim(),
        dni: extractDni(input.identifier),
        type: input.type,
        origin: input.origin,
        captured_at: input.captured_at ?? new Date().toISOString(),
        attempts: 0,
    };

    const db = await database();
    await db.put('marks', mark);

    return mark;
}

/** Marcaciones pendientes de una sesión, en el orden en que se escanearon. */
export async function listPendingMarks(
    courseId: number,
    session: number,
): Promise<QueuedMark[]> {
    const db = await database();
    const marks = await db.getAllFromIndex('marks', 'by_session', [
        courseId,
        session,
    ]);

    return marks.sort((a, b) => a.captured_at.localeCompare(b.captured_at));
}

/**
 * Marcación pendiente del mismo DNI y tipo en la sesión, si la hay (SC-49).
 * Evita guardar dos veces el mismo ingreso o la misma salida sin conexión.
 */
export async function findPendingMark(
    courseId: number,
    session: number,
    dni: string,
    type: MarkType,
): Promise<QueuedMark | undefined> {
    const pending = await listPendingMarks(courseId, session);

    return pending.find(
        (mark) =>
            mark.type === type &&
            (mark.dni ?? extractDni(mark.identifier)) === dni,
    );
}

export async function countPendingMarks(
    courseId: number,
    session: number,
): Promise<number> {
    const db = await database();

    return db.countFromIndex('marks', 'by_session', [courseId, session]);
}

/** Quita una marcación de la cola (ya la confirmó el servidor). */
export async function removeMark(clientUuid: string): Promise<void> {
    const db = await database();
    await db.delete('marks', clientUuid);
}

/**
 * Pasa una marcación de la cola a la lista de rechazadas, en una sola
 * transacción: no puede quedar en las dos ni perderse entre ambas (SC-56).
 */
export async function moveToRejected(
    mark: QueuedMark,
    result: string,
    message: string,
): Promise<RejectedMark> {
    const rejected: RejectedMark = {
        client_uuid: mark.client_uuid,
        course_id: mark.course_id,
        session: mark.session,
        identifier: mark.identifier,
        dni: mark.dni ?? extractDni(mark.identifier),
        type: mark.type,
        captured_at: mark.captured_at,
        result,
        message,
        rejected_at: new Date().toISOString(),
    };

    const db = await database();
    const tx = db.transaction(['marks', 'rejected'], 'readwrite');
    await Promise.all([
        tx.objectStore('rejected').put(rejected),
        tx.objectStore('marks').delete(mark.client_uuid),
        tx.done,
    ]);

    return rejected;
}

/** Rechazadas de una sesión, en el orden en que se escanearon. */
export async function listRejectedMarks(
    courseId: number,
    session: number,
): Promise<RejectedMark[]> {
    const db = await database();
    const rejected = await db.getAllFromIndex('rejected', 'by_session', [
        courseId,
        session,
    ]);

    return rejected.sort((a, b) => a.captured_at.localeCompare(b.captured_at));
}

/** El docente ya revisó la rechazada: se quita del dispositivo. */
export async function dismissRejectedMark(clientUuid: string): Promise<void> {
    const db = await database();
    await db.delete('rejected', clientUuid);
}

export async function dismissAllRejectedMarks(
    courseId: number,
    session: number,
): Promise<void> {
    const db = await database();
    const tx = db.transaction('rejected', 'readwrite');
    const keys = await tx.store
        .index('by_session')
        .getAllKeys([courseId, session]);
    await Promise.all([...keys.map((key) => tx.store.delete(key)), tx.done]);
}

/** Cierra la conexión; la siguiente operación la vuelve a abrir. */
export async function closeOfflineDatabase(): Promise<void> {
    if (!dbPromise) return;

    const db = await dbPromise;
    db.close();
    dbPromise = null;
}
