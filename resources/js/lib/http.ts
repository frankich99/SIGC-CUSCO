/**
 * Small JSON helpers for endpoints that answer with JSON instead of an Inertia page
 * (kiosk marks, live roster). Laravel validates the XSRF-TOKEN cookie sent back as a header.
 */

export interface JsonResult<T> {
    ok: boolean;
    status: number;
    data: T;
}

function xsrfToken(): string {
    if (typeof document === 'undefined') return '';

    const match = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='));

    return match ? decodeURIComponent(match.split('=')[1]) : '';
}

export interface RequestOptions {
    /**
     * Abortar si el servidor no responde en este tiempo. La promesa se rechaza
     * con un `NetworkTimeoutError`, igual que si no hubiera red.
     */
    timeoutMs?: number;
}

/** El servidor no respondió dentro de `timeoutMs`. */
export class NetworkTimeoutError extends Error {
    constructor(timeoutMs: number) {
        super(`Sin respuesta del servidor en ${timeoutMs} ms.`);
        this.name = 'NetworkTimeoutError';
    }
}

async function requestJson<T>(
    method: 'GET' | 'POST',
    url: string,
    body?: unknown,
    options: RequestOptions = {},
): Promise<JsonResult<T>> {
    const init: RequestInit = {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
    };

    if (method !== 'GET' && body !== undefined) {
        init.body = JSON.stringify(body);
    }

    const { timeoutMs } = options;
    let timer: ReturnType<typeof setTimeout> | null = null;
    let timedOut = false;

    if (timeoutMs !== undefined) {
        const controller = new AbortController();
        init.signal = controller.signal;
        timer = setTimeout(() => {
            timedOut = true;
            controller.abort();
        }, timeoutMs);
    }

    try {
        const response = await fetch(url, init);

        let data: unknown = null;
        try {
            data = await response.json();
        } catch {
            data = null;
        }

        return { ok: response.ok, status: response.status, data: data as T };
    } catch (error) {
        if (timedOut && timeoutMs !== undefined) {
            throw new NetworkTimeoutError(timeoutMs);
        }
        throw error;
    } finally {
        if (timer) clearTimeout(timer);
    }
}

export function getJson<T>(
    url: string,
    options?: RequestOptions,
): Promise<JsonResult<T>> {
    return requestJson<T>('GET', url, undefined, options);
}

export function postJson<T>(
    url: string,
    body: unknown = {},
    options?: RequestOptions,
): Promise<JsonResult<T>> {
    return requestJson<T>('POST', url, body, options);
}

/**
 * First validation message from a Laravel 422 payload, or its top-level message.
 */
export function firstErrorMessage(data: unknown, fallback: string): string {
    if (data && typeof data === 'object') {
        const payload = data as {
            message?: string;
            errors?: Record<string, string[]>;
        };
        const firstError = payload.errors
            ? Object.values(payload.errors)[0]?.[0]
            : undefined;

        return firstError || payload.message || fallback;
    }

    return fallback;
}
