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

async function requestJson<T>(
    method: 'GET' | 'POST',
    url: string,
    body?: unknown,
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

    const response = await fetch(url, init);

    let data: unknown = null;
    try {
        data = await response.json();
    } catch {
        data = null;
    }

    return { ok: response.ok, status: response.status, data: data as T };
}

export function getJson<T>(url: string): Promise<JsonResult<T>> {
    return requestJson<T>('GET', url);
}

export function postJson<T>(
    url: string,
    body: unknown = {},
): Promise<JsonResult<T>> {
    return requestJson<T>('POST', url, body);
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
