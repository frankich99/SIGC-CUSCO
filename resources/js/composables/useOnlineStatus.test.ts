import { describe, expect, it } from 'vitest';
import { createOnlineStatus, type ConnectionTarget } from './useOnlineStatus';

function fakeWindow(onLine = true): ConnectionTarget & {
    emit: (type: 'online' | 'offline') => void;
    listenerCount: () => number;
} {
    const events = new EventTarget();
    let count = 0;

    return {
        navigator: { onLine },
        addEventListener: (type, listener) => {
            count++;
            events.addEventListener(type, listener);
        },
        removeEventListener: (type, listener) => {
            count--;
            events.removeEventListener(type, listener);
        },
        emit: (type) => events.dispatchEvent(new Event(type)),
        listenerCount: () => count,
    };
}

describe('estado de conexión del kiosco', () => {
    it('parte de lo que informa el navegador', () => {
        expect(createOnlineStatus(fakeWindow(true)).isOffline.value).toBe(
            false,
        );
        expect(createOnlineStatus(fakeWindow(false)).isOffline.value).toBe(
            true,
        );
    });

    it('sigue los eventos online y offline del navegador', () => {
        const win = fakeWindow(true);
        const status = createOnlineStatus(win);

        win.emit('offline');
        expect(status.isOffline.value).toBe(true);

        win.emit('online');
        expect(status.isOffline.value).toBe(false);
    });

    it('queda sin conexión si falla un envío aunque el navegador diga que hay red', () => {
        const status = createOnlineStatus(fakeWindow(true));

        status.reportNetworkFailure();
        expect(status.browserOnline.value).toBe(true);
        expect(status.isOffline.value).toBe(true);

        status.reportNetworkSuccess();
        expect(status.isOffline.value).toBe(false);
    });

    it('al volver el evento online se vuelve a intentar con el servidor', () => {
        const win = fakeWindow(true);
        const status = createOnlineStatus(win);

        status.reportNetworkFailure();
        win.emit('offline');
        win.emit('online');

        expect(status.serverUnreachable.value).toBe(false);
        expect(status.isOffline.value).toBe(false);
    });

    it('deja de escuchar al detenerse', () => {
        const win = fakeWindow(true);
        const status = createOnlineStatus(win);
        expect(win.listenerCount()).toBe(2);

        status.stop();
        expect(win.listenerCount()).toBe(0);

        win.emit('offline');
        expect(status.isOffline.value).toBe(false);
    });

    it('sin window (SSR) asume que hay conexión', () => {
        const status = createOnlineStatus(null);
        expect(status.isOffline.value).toBe(false);
        status.stop();
    });
});
