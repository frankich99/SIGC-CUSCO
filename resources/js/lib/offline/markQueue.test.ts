import 'fake-indexeddb/auto';
import { afterEach, describe, expect, it } from 'vitest';
import {
    OFFLINE_DB_NAME,
    closeOfflineDatabase,
    countPendingMarks,
    enqueueMark,
    generateClientUuid,
    listPendingMarks,
    removeMark,
} from './markQueue';

const UUID_V4 =
    /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;

async function resetDatabase(): Promise<void> {
    await closeOfflineDatabase();
    await new Promise<void>((resolve, reject) => {
        const request = indexedDB.deleteDatabase(OFFLINE_DB_NAME);
        request.onsuccess = () => resolve();
        request.onerror = () => reject(request.error);
    });
}

describe('cola local de marcaciones del kiosco', () => {
    afterEach(resetDatabase);

    it('guarda una marcación y la lee de vuelta', async () => {
        const saved = await enqueueMark({
            course_id: 7,
            session: 2,
            identifier: ' 45678912 ',
            type: 'entrada',
            origin: 'dni_lector',
        });

        expect(saved.client_uuid).toMatch(UUID_V4);
        expect(saved.identifier).toBe('45678912');
        expect(saved.attempts).toBe(0);

        const pending = await listPendingMarks(7, 2);
        expect(pending).toEqual([saved]);
    });

    it('la marcación sigue guardada al reabrir la base de datos', async () => {
        const saved = await enqueueMark({
            course_id: 7,
            session: 2,
            identifier: '45678912',
            type: 'entrada',
            origin: 'dni_lector',
        });

        // Simula recargar la página del kiosco.
        await closeOfflineDatabase();

        expect(await listPendingMarks(7, 2)).toEqual([saved]);
    });

    it('separa las marcaciones por curso y sesión', async () => {
        const base = {
            identifier: '45678912',
            type: 'entrada' as const,
            origin: 'dni_lector' as const,
        };
        await enqueueMark({ ...base, course_id: 7, session: 1 });
        await enqueueMark({ ...base, course_id: 7, session: 2 });
        await enqueueMark({ ...base, course_id: 8, session: 2 });

        expect(await countPendingMarks(7, 2)).toBe(1);
        expect(await countPendingMarks(7, 1)).toBe(1);
        expect(await countPendingMarks(9, 1)).toBe(0);
    });

    it('lista en el orden en que se escanearon', async () => {
        const base = {
            course_id: 7,
            session: 2,
            type: 'entrada' as const,
            origin: 'dni_lector' as const,
        };
        await enqueueMark({
            ...base,
            identifier: '22222222',
            captured_at: '2026-11-17T15:05:00.000Z',
        });
        await enqueueMark({
            ...base,
            identifier: '11111111',
            captured_at: '2026-11-17T15:00:00.000Z',
        });

        const pending = await listPendingMarks(7, 2);
        expect(pending.map((m) => m.identifier)).toEqual([
            '11111111',
            '22222222',
        ]);
    });

    it('el mismo client_uuid no crea una segunda marcación', async () => {
        const mark = {
            client_uuid: generateClientUuid(),
            course_id: 7,
            session: 2,
            identifier: '45678912',
            type: 'salida' as const,
            origin: 'manual' as const,
        };
        await enqueueMark(mark);
        await enqueueMark(mark);

        expect(await countPendingMarks(7, 2)).toBe(1);
    });

    it('quita la marcación de la cola cuando ya se sincronizó', async () => {
        const saved = await enqueueMark({
            course_id: 7,
            session: 2,
            identifier: '45678912',
            type: 'entrada',
            origin: 'dni_lector',
        });

        await removeMark(saved.client_uuid);

        expect(await countPendingMarks(7, 2)).toBe(0);
    });
});

describe('generateClientUuid', () => {
    it('genera UUID v4 distintos', () => {
        const ids = new Set(Array.from({ length: 50 }, generateClientUuid));
        expect(ids.size).toBe(50);
        for (const id of ids) expect(id).toMatch(UUID_V4);
    });

    it('funciona sin crypto.randomUUID (kiosco abierto por HTTP)', () => {
        const ownDescriptor = Object.getOwnPropertyDescriptor(
            crypto,
            'randomUUID',
        );
        Object.defineProperty(crypto, 'randomUUID', {
            value: undefined,
            configurable: true,
        });
        try {
            expect(generateClientUuid()).toMatch(UUID_V4);
        } finally {
            if (ownDescriptor) {
                Object.defineProperty(crypto, 'randomUUID', ownDescriptor);
            } else {
                Reflect.deleteProperty(crypto, 'randomUUID');
            }
        }
        expect(typeof crypto.randomUUID).toBe('function');
    });
});
