import { describe, expect, it } from 'vitest';
import { syncBadge } from './syncStatus';

const base = { offline: false, syncing: false, pending: 0, synced: 0 };

describe('indicador de sincronización del kiosco', () => {
    it('no muestra nada en línea y sin marcaciones guardadas', () => {
        expect(syncBadge(base)).toEqual({ state: 'hidden', label: '' });
    });

    it('sin conexión avisa y cuenta las pendientes', () => {
        expect(syncBadge({ ...base, offline: true })).toEqual({
            state: 'offline',
            label: 'Sin conexión',
        });
        expect(syncBadge({ ...base, offline: true, pending: 1 }).label).toBe(
            'Sin conexión · 1 pendiente',
        );
        expect(syncBadge({ ...base, offline: true, pending: 3 }).label).toBe(
            'Sin conexión · 3 pendientes',
        );
    });

    it('sin conexión tiene prioridad aunque esté intentando enviar', () => {
        expect(
            syncBadge({ ...base, offline: true, syncing: true, pending: 2 })
                .state,
        ).toBe('offline');
    });

    it('muestra el envío en curso', () => {
        expect(syncBadge({ ...base, syncing: true, pending: 4 })).toEqual({
            state: 'syncing',
            label: 'Enviando 4 pendientes…',
        });
    });

    it('con red y pendientes sin enviar aún', () => {
        expect(syncBadge({ ...base, pending: 2 })).toEqual({
            state: 'pending',
            label: '2 pendientes de enviar',
        });
    });

    it('confirma cuando ya se envió todo lo guardado', () => {
        expect(syncBadge({ ...base, synced: 5 })).toEqual({
            state: 'synced',
            label: 'Todo sincronizado',
        });
    });

    it('ignora conteos negativos', () => {
        expect(syncBadge({ ...base, pending: -1 }).state).toBe('hidden');
    });
});
