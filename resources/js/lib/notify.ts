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

// Estado reactivo global compartido de toasts: garantiza MÁXIMO 1 toast activo
export const activeToasts = ref<ActiveToast[]>([]);

let toastCounter = 0;
let lastProcessedFlash: string | null = null;
let currentTimerId: ReturnType<typeof setTimeout> | null = null;
let lastToastSignature: string = '';
let lastToastTimestamp: number = 0;

export function addToast(options: ToastOptions | string): number {
    const opts: ToastOptions = typeof options === 'string' ? { title: options } : options;
    const title = (opts.title || '').trim();
    const text = (opts.text || '').trim();
    const icon = opts.icon || 'success';
    const signature = `${icon}:${title}:${text}`;
    const now = Date.now();

    // Debounce: no duplicar la misma notificación en menos de 1500ms
    if (signature === lastToastSignature && now - lastToastTimestamp < 1500) {
        return activeToasts.value[0]?.id || 0;
    }
    lastToastSignature = signature;
    lastToastTimestamp = now;

    // Limpiar temporizador previo para evitar que cierre prematuramente la nueva notificación
    if (currentTimerId) {
        clearTimeout(currentTimerId);
        currentTimerId = null;
    }

    const id = ++toastCounter;
    const duration = opts.timer !== undefined ? opts.timer : 3500;

    const toast: ActiveToast = {
        ...opts,
        id,
        icon,
        position: opts.position || 'top-end',
        timer: duration,
        timerProgressBar: opts.timerProgressBar !== undefined ? opts.timerProgressBar : true,
        remaining: duration,
        createdAt: now,
    };

    // REEMPLAZAR: estrictamente una sola notificación visible en pantalla a la vez
    activeToasts.value = [toast];

    // Auto-eliminar cuando expira el temporizador
    if (duration > 0) {
        currentTimerId = setTimeout(() => {
            removeToast(id);
            currentTimerId = null;
        }, duration);
    }

    return id;
}

export function removeToast(id: number): void {
    if (currentTimerId) {
        clearTimeout(currentTimerId);
        currentTimerId = null;
    }
    activeToasts.value = activeToasts.value.filter((t) => t.id !== id);
}

export function clearToasts(): void {
    if (currentTimerId) {
        clearTimeout(currentTimerId);
        currentTimerId = null;
    }
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
let isListenerInitialized = false;

export function setupInertiaFlashListener(): void {
    if (typeof window === 'undefined' || isListenerInitialized) return;
    isListenerInitialized = true;

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

    // Escuchar únicamente evento finish de Inertia al completar navegación
    router.on('finish', (event: any) => {
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
