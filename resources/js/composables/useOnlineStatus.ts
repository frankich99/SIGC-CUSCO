/**
 * Estado de conexión del kiosco (SC-48 / HU-11.2).
 *
 * Combina lo que informa el navegador (`navigator.onLine` y los eventos
 * `online`/`offline`) con lo que observa el propio kiosco: si un envío falla
 * por red, se considera sin conexión aunque el navegador diga lo contrario
 * (p. ej. Wi-Fi conectado pero sin salida a internet).
 */
import { computed, onUnmounted, ref, type ComputedRef, type Ref } from 'vue';

export interface ConnectionTarget {
    addEventListener(type: 'online' | 'offline', listener: () => void): void;
    removeEventListener(type: 'online' | 'offline', listener: () => void): void;
    navigator?: { onLine?: boolean };
}

export interface OnlineStatus {
    /** Lo que informa el navegador. */
    browserOnline: Ref<boolean>;
    /** Falló el último envío por red. */
    serverUnreachable: Ref<boolean>;
    /** Hay que guardar en la cola local en vez de enviar. */
    isOffline: ComputedRef<boolean>;
    reportNetworkFailure: () => void;
    reportNetworkSuccess: () => void;
    stop: () => void;
}

function defaultTarget(): ConnectionTarget | null {
    return typeof window === 'undefined' ? null : window;
}

export function createOnlineStatus(
    target: ConnectionTarget | null = defaultTarget(),
): OnlineStatus {
    const browserOnline = ref(target?.navigator?.onLine ?? true);
    const serverUnreachable = ref(false);

    const handleOnline = (): void => {
        browserOnline.value = true;
        // Volvió la red: el próximo envío dirá si el servidor responde.
        serverUnreachable.value = false;
    };
    const handleOffline = (): void => {
        browserOnline.value = false;
    };

    target?.addEventListener('online', handleOnline);
    target?.addEventListener('offline', handleOffline);

    return {
        browserOnline,
        serverUnreachable,
        isOffline: computed(
            () => !browserOnline.value || serverUnreachable.value,
        ),
        reportNetworkFailure: () => {
            serverUnreachable.value = true;
        },
        reportNetworkSuccess: () => {
            serverUnreachable.value = false;
        },
        stop: () => {
            target?.removeEventListener('online', handleOnline);
            target?.removeEventListener('offline', handleOffline);
        },
    };
}

/** Versión para componentes: deja de escuchar al desmontarse. */
export function useOnlineStatus(): OnlineStatus {
    const status = createOnlineStatus();
    onUnmounted(status.stop);

    return status;
}
