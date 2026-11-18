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

interface KioskOfflineDB extends DBSchema {
    marks: {
        key: string;
        value: QueuedMark;
        indexes: { by_session: [number, number] };
    };
}

export const OFFLINE_DB_NAME = 'sigc-kiosco-offline';
const OFFLINE_DB_VERSION = 1;

let dbPromise: Promise<IDBPDatabase<KioskOfflineDB>> | null = null;

function database(): Promise<IDBPDatabase<KioskOfflineDB>> {
    dbPromise ??= openDB<KioskOfflineDB>(OFFLINE_DB_NAME, OFFLINE_DB_VERSION, {
        upgrade(db) {
            const store = db.createObjectStore('marks', {
                keyPath: 'client_uuid',
            });
            store.createIndex('by_session', ['course_id', 'session']);
        },
    });

    return dbPromise;
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

/** Cierra la conexión; la siguiente operación la vuelve a abrir. */
export async function closeOfflineDatabase(): Promise<void> {
    if (!dbPromise) return;

    const db = await dbPromise;
    db.close();
    dbPromise = null;
}
