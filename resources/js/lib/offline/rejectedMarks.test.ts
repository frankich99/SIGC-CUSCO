import 'fake-indexeddb/auto';
import { afterEach, describe, expect, it } from 'vitest';
import {
    OFFLINE_DB_NAME,
    closeOfflineDatabase,
    countPendingMarks,
    dismissAllRejectedMarks,
    dismissRejectedMark,
    enqueueMark,
    listPendingMarks,
    listRejectedMarks,
    moveToRejected,
} from './markQueue';

async function resetDatabase(): Promise<void> {
    await closeOfflineDatabase();
    await new Promise<void>((resolve, reject) => {
        const request = indexedDB.deleteDatabase(OFFLINE_DB_NAME);
        request.onsuccess = () => resolve();
        request.onerror = () => reject(request.error);
    });
}

async function queued(dni: string, capturedAt: string, session = 2) {
    return enqueueMark({
        course_id: 7,
        session,
        identifier: dni,
        type: 'entrada',
        origin: 'dni_lector',
        captured_at: capturedAt,
    });
}

describe('marcaciones rechazadas al sincronizar (SC-56)', () => {
    afterEach(resetDatabase);

    it('mover a rechazadas la saca de la cola en la misma operación', async () => {
        const mark = await queued('87654321', '2026-11-17T14:00:00.000Z');

        const rejected = await moveToRejected(
            mark,
            'no_inscrito',
            'DNI no inscrito en esta capacitación.',
        );

        expect(rejected.dni).toBe('87654321');
        expect(rejected.rejected_at).toMatch(/^\d{4}-\d{2}-\d{2}T/);
        expect(await countPendingMarks(7, 2)).toBe(0);
        expect(await listRejectedMarks(7, 2)).toEqual([rejected]);
    });

    it('lista por sesión en el orden en que se escanearon', async () => {
        const late = await queued('22222222', '2026-11-17T14:05:00.000Z');
        const early = await queued('11111111', '2026-11-17T14:00:00.000Z');
        const other = await queued('33333333', '2026-11-17T14:00:00.000Z', 3);
        await moveToRejected(late, 'no_inscrito', 'x');
        await moveToRejected(early, 'sin_ingreso', 'y');
        await moveToRejected(other, 'no_inscrito', 'z');

        const rejected = await listRejectedMarks(7, 2);
        expect(rejected.map((r) => r.dni)).toEqual(['11111111', '22222222']);
    });

    it('sigue ahí al recargar el kiosco', async () => {
        const mark = await queued('87654321', '2026-11-17T14:00:00.000Z');
        await moveToRejected(mark, 'no_inscrito', 'x');

        await closeOfflineDatabase();

        expect(await listRejectedMarks(7, 2)).toHaveLength(1);
    });

    it('el docente puede descartar una o todas las de la sesión', async () => {
        const a = await queued('11111111', '2026-11-17T14:00:00.000Z');
        const b = await queued('22222222', '2026-11-17T14:01:00.000Z');
        const c = await queued('33333333', '2026-11-17T14:02:00.000Z');
        const other = await queued('44444444', '2026-11-17T14:00:00.000Z', 3);
        for (const mark of [a, b, c, other]) {
            await moveToRejected(mark, 'no_inscrito', 'x');
        }

        await dismissRejectedMark(a.client_uuid);
        expect((await listRejectedMarks(7, 2)).map((r) => r.dni)).toEqual([
            '22222222',
            '33333333',
        ]);

        await dismissAllRejectedMarks(7, 2);
        expect(await listRejectedMarks(7, 2)).toEqual([]);
        // Las de otra sesión no se tocan.
        expect(await listRejectedMarks(7, 3)).toHaveLength(1);
    });
});

describe('actualización de la base del dispositivo (v1 → v2)', () => {
    afterEach(resetDatabase);

    it('conserva las marcaciones guardadas con la versión anterior', async () => {
        // Base como la dejó el prototipo SC-46: versión 1, solo «marks».
        await new Promise<void>((resolve, reject) => {
            const request = indexedDB.open(OFFLINE_DB_NAME, 1);
            request.onupgradeneeded = () => {
                const store = request.result.createObjectStore('marks', {
                    keyPath: 'client_uuid',
                });
                store.createIndex('by_session', ['course_id', 'session']);
                store.put({
                    client_uuid: '0b6c5b1e-6d1f-4b8e-9a51-2f3c4d5e6f70',
                    course_id: 7,
                    session: 2,
                    identifier: '45678912',
                    type: 'entrada',
                    origin: 'dni_lector',
                    captured_at: '2026-11-17T14:00:00.000Z',
                    attempts: 0,
                });
            };
            request.onsuccess = () => {
                request.result.close();
                resolve();
            };
            request.onerror = () => reject(request.error);
        });

        const pending = await listPendingMarks(7, 2);
        expect(pending.map((m) => m.identifier)).toEqual(['45678912']);
        // Y ya existe el almacén nuevo.
        expect(await listRejectedMarks(7, 2)).toEqual([]);
    });
});
