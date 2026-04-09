/**
 * APIクライアント共通ラッパー
 * Laravel の CSRF トークンを自動付与する fetch ラッパー。
 */
const BASE = '/api';

async function request(method, path, body = null, isFormData = false) {
    const headers = {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
    };
    if (!isFormData) {
        headers['Content-Type'] = 'application/json';
        headers['Accept'] = 'application/json';
    }

    const res = await fetch(BASE + path, {
        method,
        headers,
        body: body ? (isFormData ? body : JSON.stringify(body)) : undefined,
        credentials: 'same-origin',
    });

    const json = await res.json().catch(() => null);

    if (!res.ok) {
        const err = new Error(json?.message ?? `HTTP ${res.status}`);
        err.errors = json?.errors ?? {};
        err.status = res.status;
        throw err;
    }
    return json;
}

export const api = {
    get:    (path)         => request('GET',    path),
    post:   (path, body)   => request('POST',   path, body),
    put:    (path, body)   => request('PUT',    path, body),
    patch:  (path, body)   => request('PATCH',  path, body),
    delete: (path)         => request('DELETE', path),
    upload: (path, form)   => request('POST',   path, form, true),
    uploadPut: (path, form) => {
        if (form instanceof FormData && !form.has('_method')) {
            form.append('_method', 'PUT');
        }
        return request('POST', path, form, true);
    },
};
