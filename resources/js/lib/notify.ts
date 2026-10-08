import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

export type ToastIcon = 'success' | 'error' | 'warning' | 'info' | 'question';
export type ToastPosition = 'top-end' | 'top-start' | 'top-center' | 'bottom-end' | 'bottom-start' | 'bottom-center' | 'center';

export interface ToastOptions {
    id?: string | number;
    icon?: ToastIcon;
    title: string;
    text?: string;
    position?: ToastPosition;
    timer?: number;
    timerProgressBar?: boolean;
    showConfirmButton?: boolean;
}

export interface ActiveToast extends ToastOptions {
    id: number;
    icon: ToastIcon;
    position: ToastPosition;
    timer: number;
    timerProgressBar: boolean;
    remaining: number;
    createdAt: number;
}

// Estado reactivo global compartido de toasts
export const activeToasts = ref<ActiveToast[]>([]);

let toastCounter = 0;
let lastProcessedFlash: string | null = null;

export function addToast(options: ToastOptions | string): number {
    const opts: ToastOptions = typeof options === 'string' ? { title: options } : options;
    const id = ++toastCounter;

    const toast: ActiveToast = {
        ...opts,
        id,
        icon: opts.icon || 'success',
        position: opts.position || 'top-end',
        timer: opts.timer !== undefined ? opts.timer : 4000,
        timerProgressBar: opts.timerProgressBar !== undefined ? opts.timerProgressBar : true,
        remaining: opts.timer !== undefined ? opts.timer : 4000,
        createdAt: Date.now(),
    };

    // Agregar a la lista
    activeToasts.value.push(toast);

    // Auto-eliminar cuando expira el temporizador
    if (toast.timer > 0) {
        setTimeout(() => {
            removeToast(id);
        }, toast.timer);
    }

    return id;
}

export function removeToast(id: number): void {
    const idx = activeToasts.value.findIndex((t) => t.id === id);
    if (idx !== -1) {
        activeToasts.value.splice(idx, 1);
    }
}

export function clearToasts(): void {
    activeToasts.value = [];
}

// API de notificación SweetAlert2 compatible y simplificada (Estilo FUNDO_PARQUE)
export const notify = {
    fire(options: ToastOptions | string): number {
        return addToast(options);
    },
    success(title: string, text?: string, timer = 4000): number {
        return addToast({ icon: 'success', title, text, timer });
    },
    error(title: string, text?: string, timer = 5000): number {
        return addToast({ icon: 'error', title, text, timer });
    },
    warning(title: string, text?: string, timer = 4500): number {
        return addToast({ icon: 'warning', title, text, timer });
    },
    info(title: string, text?: string, timer = 4000): number {
        return addToast({ icon: 'info', title, text, timer });
    },
    remove: removeToast,
    clear: clearToasts,
};

// Exponer Swal en window para compatibilidad directa con SweetAlert2
if (typeof window !== 'undefined') {
    (window as any).Swal = {
        fire: (opts: ToastOptions | string) => notify.fire(opts),
        mixin: (baseOpts: Partial<ToastOptions>) => ({
            fire: (opts: ToastOptions | string) => {
                const merged = typeof opts === 'string' ? { ...baseOpts, title: opts } : { ...baseOpts, ...opts };
                return notify.fire(merged as ToastOptions);
            },
        }),
        toast: notify.fire,
    };
}

// Listener global para mensajes flash de Laravel / Inertia
export function setupInertiaFlashListener(): void {
    if (typeof window === 'undefined') return;

    function checkFlash(pageProps: any) {
        const flash = pageProps?.flash as Record<string, string | null> | undefined;
        if (!flash) return;

        const flashKey = JSON.stringify(flash);
        if (flashKey === lastProcessedFlash || flashKey === '{}' || flashKey === 'null') {
            return;
        }

        lastProcessedFlash = flashKey;

        if (flash.success) {
            notify.success(flash.success);
        } else if (flash.error) {
            notify.error(flash.error);
        } else if (flash.warning) {
            notify.warning(flash.warning);
        } else if (flash.info) {
            notify.info(flash.info);
        } else if (flash.message) {
            notify.info(flash.message);
        } else if (flash.status) {
            notify.success(flash.status);
        }
    }

    // Escuchar eventos de navegación de Inertia
    router.on('finish', (event: any) => {
        // En Inertia v2/v3 event.detail?.page?.props tiene los nuevos props
        const props = event?.detail?.page?.props;
        if (props) {
            checkFlash(props);
        }
    });

    router.on('success', (event: any) => {
        const props = event?.detail?.page?.props;
        if (props) {
            checkFlash(props);
        }
    });

    // Revisar mensaje flash en la carga inicial de página (SSR o hidratación)
    const checkInitialFlash = () => {
        try {
            const appEl = document.getElementById('app');
            if (appEl?.dataset?.page) {
                const parsed = JSON.parse(appEl.dataset.page);
                if (parsed?.props) {
                    checkFlash(parsed.props);
                }
            }
        } catch {
            // Silencioso
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', checkInitialFlash, { once: true });
    } else {
        setTimeout(checkInitialFlash, 50);
    }
}
