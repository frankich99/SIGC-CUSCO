import { afterEach, describe, expect, it, vi } from 'vitest';
import { NetworkTimeoutError, postJson } from './http';

afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
});

/** fetch que nunca responde, salvo que lo aborten. */
function hangingFetch(): typeof fetch {
    return vi.fn(
        (_url: RequestInfo | URL, init?: RequestInit) =>
            new Promise<Response>((_resolve, reject) => {
                init?.signal?.addEventListener('abort', () =>
                    reject(new DOMException('Aborted', 'AbortError')),
                );
            }),
    );
}

describe('postJson', () => {
    it('devuelve el estado y el JSON de la respuesta', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () =>
                Response.json({ result: 'asistida' }, { status: 200 }),
            ),
        );

        await expect(postJson('/marks', { a: 1 })).resolves.toEqual({
            ok: true,
            status: 200,
            data: { result: 'asistida' },
        });
    });

    it('una respuesta de error del servidor no es un fallo de red', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () =>
                Response.json({ result: 'duplicada' }, { status: 409 }),
            ),
        );

        const result = await postJson('/marks', {}, { timeoutMs: 8000 });
        expect(result.status).toBe(409);
        expect(result.ok).toBe(false);
    });

    it('rechaza con NetworkTimeoutError si el servidor no responde a tiempo', async () => {
        vi.useFakeTimers();
        vi.stubGlobal('fetch', hangingFetch());

        const request = postJson('/marks', {}, { timeoutMs: 8000 });
        const assertion =
            expect(request).rejects.toBeInstanceOf(NetworkTimeoutError);

        await vi.advanceTimersByTimeAsync(8000);
        await assertion;
    });

    it('sin timeoutMs no aborta la petición', async () => {
        vi.useFakeTimers();
        const fetchMock = hangingFetch();
        vi.stubGlobal('fetch', fetchMock);

        let settled = false;
        void postJson('/marks', {}).finally(() => (settled = true));

        await vi.advanceTimersByTimeAsync(60_000);
        expect(settled).toBe(false);
        expect(vi.mocked(fetchMock).mock.calls[0][1]?.signal).toBeUndefined();
    });

    it('un error de red se propaga tal cual', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => {
                throw new TypeError('Failed to fetch');
            }),
        );

        await expect(
            postJson('/marks', {}, { timeoutMs: 8000 }),
        ).rejects.toThrow('Failed to fetch');
    });
});
