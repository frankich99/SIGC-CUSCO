/**
 * Indicador del estado de sincronización del kiosco (SC-55 / HU-11.4).
 *
 * Decide qué mostrar en el encabezado a partir del estado de conexión y de la
 * cola, para que el docente sepa de un vistazo si hay marcaciones sin enviar.
 */
export type SyncBadgeState =
    | 'offline'
    | 'syncing'
    | 'pending'
    | 'synced'
    | 'hidden';

export interface SyncBadgeInput {
    offline: boolean;
    syncing: boolean;
    /** Marcaciones de la sesión que siguen en el dispositivo. */
    pending: number;
    /** Marcaciones guardadas que ya se enviaron desde que se abrió el kiosco. */
    synced: number;
}

export interface SyncBadge {
    state: SyncBadgeState;
    label: string;
}

function pendingLabel(count: number): string {
    return count === 1 ? '1 pendiente' : `${count} pendientes`;
}

export function syncBadge(input: SyncBadgeInput): SyncBadge {
    const pending = Math.max(0, input.pending);

    if (input.offline) {
        return {
            state: 'offline',
            label:
                pending > 0
                    ? `Sin conexión · ${pendingLabel(pending)}`
                    : 'Sin conexión',
        };
    }

    if (input.syncing && pending > 0) {
        return {
            state: 'syncing',
            label: `Enviando ${pendingLabel(pending)}…`,
        };
    }

    if (pending > 0) {
        return {
            state: 'pending',
            label: `${pendingLabel(pending)} de enviar`,
        };
    }

    if (input.synced > 0) {
        return { state: 'synced', label: 'Todo sincronizado' };
    }

    // En línea y sin nada guardado: no hace falta mostrar nada.
    return { state: 'hidden', label: '' };
}
