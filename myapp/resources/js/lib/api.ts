/** fetch with Laravel's CSRF cookie, for the JSON endpoints (receipt polling, chat). */
export class ApiError extends Error {
    constructor(
        public status: number,
        public data: {
            message?: string;
            errors?: Record<string, string[]>;
            manual_url?: string;
        } | null,
    ) {
        super(data?.message ?? 'Something went wrong. Try again.');
    }
}

const xsrf = () =>
    decodeURIComponent(
        document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
    );

export async function api<T>(
    url: string,
    body?: FormData | Record<string, unknown>,
    method = body === undefined ? 'GET' : 'POST',
): Promise<T> {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-XSRF-TOKEN': xsrf(),
    };

    if (body && !(body instanceof FormData)) {
        headers['Content-Type'] = 'application/json';
    }

    const init: RequestInit = { method, headers, credentials: 'same-origin' };

    if (body !== undefined) {
        init.body = body instanceof FormData ? body : JSON.stringify(body);
    }

    const res = await fetch(url, init);
    const data = res.status === 204 ? null : await res.json().catch(() => null);

    if (!res.ok) {
        throw new ApiError(res.status, data);
    }

    return data as T;
}
