import 'fake-indexeddb/auto';
import { afterEach, describe, expect, it } from 'vitest';
import {
    OFFLINE_DB_NAME,
    closeOfflineDatabase,
    countPendingMarks,
    extractDni,
    findPendingMark,
} from './markQueue';
import { queueOfflineMark } from './queueOfflineMark';

async function resetDatabase(): Promise<void> {
    await closeOfflineDatabase();
    await new Promise<void>((resolve, reject) => {
        const request = indexedDB.deleteDatabase(OFFLINE_DB_NAME);
        request.onsuccess = () => resolve();
        request.onerror = () => reject(request.error);
    });
}

const entry = {
    courseId: 7,
    session: 2,
    type: 'entrada' as const,
    origin: 'dni_lector' as const,
};

describe('extractDni', () => {
    it('toma los 8 dígitos como el servidor', () => {
        expect(extractDni('45678912')).toBe('45678912');
        expect(extractDni(' 45678912 ')).toBe('45678912');
        // El lector puede mandar más datos alrededor del número.
        expect(extractDni('@45678912@PE')).toBe('45678912');
    });

    it('devuelve null si no hay 8 dígitos seguidos', () => {
        expect(extractDni('4567891')).toBeNull();
        expect(extractDni('ABC')).toBeNull();
        expect(extractDni('')).toBeNull();
    });
});

describe('guardar una lectura sin conexión', () => {
    afterEach(resetDatabase);

    it('guarda la marcación con el DNI extraído', async () => {
        const outcome = await queueOfflineMark({
            ...entry,
            identifier: '@45678912@PE',
        });

        expect(outcome.kind).toBe('saved');
        if (outcome.kind !== 'saved') return;
        expect(outcome.mark.dni).toBe('45678912');
        expect(outcome.mark.identifier).toBe('@45678912@PE');
        expect(await countPendingMarks(7, 2)).toBe(1);
    });

    it('rechaza una lectura sin DNI válido y no la guarda', async () => {
        const outcome = await queueOfflineMark({
            ...entry,
            identifier: '1234',
        });

        expect(outcome).toEqual({ kind: 'invalid_dni' });
        expect(await countPendingMarks(7, 2)).toBe(0);
    });

    it('no repite el mismo ingreso del mismo DNI en la sesión', async () => {
        const first = await queueOfflineMark({
            ...entry,
            identifier: '45678912',
        });
        const second = await queueOfflineMark({
            ...entry,
            identifier: '45678912',
        });

        expect(second.kind).toBe('already_pending');
        if (first.kind !== 'saved' || second.kind !== 'already_pending') {
            return;
        }
        expect(second.mark.client_uuid).toBe(first.mark.client_uuid);
        expect(await countPendingMarks(7, 2)).toBe(1);
    });

    it('lo detecta aunque el lector mande el DNI con otros caracteres', async () => {
        await queueOfflineMark({ ...entry, identifier: '45678912' });
        const second = await queueOfflineMark({
            ...entry,
            identifier: '@45678912@PE',
        });

        expect(second.kind).toBe('already_pending');
    });

    it('permite el ingreso y luego la salida del mismo DNI', async () => {
        await queueOfflineMark({ ...entry, identifier: '45678912' });
        const exit = await queueOfflineMark({
            ...entry,
            type: 'salida',
            identifier: '45678912',
        });

        expect(exit.kind).toBe('saved');
        expect(await countPendingMarks(7, 2)).toBe(2);
    });

    it('el mismo DNI en otra sesión es otra marcación', async () => {
        await queueOfflineMark({ ...entry, identifier: '45678912' });
        const other = await queueOfflineMark({
            ...entry,
            session: 3,
            identifier: '45678912',
        });

        expect(other.kind).toBe('saved');
    });

    it('reconoce marcaciones guardadas antes de existir el campo dni', async () => {
        // Las marcaciones del prototipo (SC-46) no tenían `dni`.
        const { enqueueMark } = await import('./markQueue');
        const legacy = await enqueueMark({
            course_id: 7,
            session: 2,
            identifier: '45678912',
            type: 'entrada',
            origin: 'dni_lector',
        });
        const db = await new Promise<IDBDatabase>((resolve, reject) => {
            const request = indexedDB.open(OFFLINE_DB_NAME);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
        await new Promise<void>((resolve, reject) => {
            const tx = db.transaction('marks', 'readwrite');
            const { dni: _dni, ...withoutDni } = legacy;
            tx.objectStore('marks').put(withoutDni);
            tx.oncomplete = () => resolve();
            tx.onerror = () => reject(tx.error);
        });
        db.close();

        const found = await findPendingMark(7, 2, '45678912', 'entrada');
        expect(found?.client_uuid).toBe(legacy.client_uuid);
    });
});
