let token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

export class ApiError extends Error {
    constructor(message, status = 0, errors = {}) {
        super(message);
        this.status = status;
        this.errors = errors;
    }
}

function responseErrorMessage(status, data) {
    if (status === 419) {
        return 'Session expired. Reload the page and sign in again.';
    }
    if (status === 429) {
        return 'Too many attempts. Wait a minute and try again.';
    }
    if (status >= 500) {
        return 'The server could not complete the request. Try again.';
    }

    return Object.values(data.errors ?? {}).flat()[0] ?? data.message ?? 'Request failed.';
}

export async function api(path, options = {}) {
    let response;
    const headers = { Accept: 'application/json', 'X-CSRF-TOKEN': token, ...options.headers };
    if (options.body && !(options.body instanceof FormData)) {
        headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(options.body);
    }
    try {
        response = await fetch('/api/v1/' + path, {
            ...options,
            headers,
            credentials: 'same-origin',
            cache: 'no-store',
        });
    } catch {
        throw new ApiError('Server unavailable. Check your home network connection and try again.');
    }
    const data = response.status === 204 ? null : await response.json().catch(() => ({}));
    if (!response.ok) {
        if (response.status === 401) {
            window.dispatchEvent(new Event('inventory-unauthorized'));
        }
        const message = responseErrorMessage(response.status, data);
        throw new ApiError(message, response.status, data.errors);
    }
    if (data?.csrf_token) {
        token = data.csrf_token;
    }
    return data;
}
