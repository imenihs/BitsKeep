/**
 * APIクライアント共通ラッパー
 * Laravel の CSRF トークンを自動付与する fetch ラッパー。
 */
const BASE = '/api';

function requestFormDataWithXhr(method, path, form, options = {}) {
    const {
        timeoutMs = 0,
        timeoutMessage = '',
        onEvent = null,
    } = options ?? {};

    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        const emit = (type, detail = {}) => {
            if (typeof onEvent === 'function') {
                onEvent(type, detail);
            }
        };

        xhr.open(method, BASE + path, true);
        xhr.withCredentials = true;
        xhr.responseType = 'text';
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]')?.content ?? '');
        xhr.setRequestHeader('Accept', 'application/json');
        if (timeoutMs > 0) {
            xhr.timeout = timeoutMs;
        }
        emit('open', {
            method,
            path,
            timeoutMs,
        });

        xhr.onreadystatechange = () => {
            emit('ready_state', {
                readyState: xhr.readyState,
                status: xhr.status,
            });
        };

        xhr.onloadstart = () => {
            emit('load_start', {});
        };

        xhr.upload.onprogress = (event) => {
            emit('upload_progress', {
                loaded: event.loaded,
                total: event.total,
                lengthComputable: event.lengthComputable,
            });
        };

        xhr.onload = () => {
            const json = (() => {
                try {
                    return xhr.responseText ? JSON.parse(xhr.responseText) : null;
                } catch {
                    return null;
                }
            })();

            if (xhr.status >= 200 && xhr.status < 300) {
                emit('load', {
                    status: xhr.status,
                    success: true,
                });
                resolve(json);
                return;
            }

            const err = new Error(json?.message ?? `HTTP ${xhr.status}`);
            err.errors = json?.errors ?? {};
            err.status = xhr.status;
            emit('load', {
                status: xhr.status,
                success: false,
                message: err.message,
            });
            reject(err);
        };

        xhr.onerror = () => {
            emit('error', {
                status: xhr.status,
            });
            reject(new Error('通信エラーが発生しました。'));
        };

        xhr.onabort = () => {
            emit('abort', {
                status: xhr.status,
            });
        };

        xhr.ontimeout = () => {
            const err = new Error(timeoutMessage || `通信がタイムアウトしました: ${method} ${path}`);
            err.code = 'timeout';
            emit('timeout', {
                timeoutMs,
                status: xhr.status,
            });
            reject(err);
        };

        emit('send', {});
        xhr.send(form);
    });
}

async function request(method, path, body = null, isFormData = false, options = {}) {
    const {
        timeoutMs = 0,
        timeoutMessage = '',
    } = options ?? {};
    const useFormData = isFormData || (typeof FormData !== 'undefined' && body instanceof FormData);
    if (useFormData && body instanceof FormData && options.transport === 'xhr') {
        return requestFormDataWithXhr(method, path, body, options);
    }
    const headers = {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        'Accept': 'application/json',
    };
    if (!useFormData) {
        headers['Content-Type'] = 'application/json';
    }

    const controller = timeoutMs > 0 && typeof AbortController !== 'undefined'
        ? new AbortController()
        : null;
    let timeoutId = null;

    try {
        if (controller) {
            timeoutId = window.setTimeout(() => {
                controller.abort();
            }, timeoutMs);
        }

        const res = await fetch(BASE + path, {
            method,
            headers,
            body: body === null || body === undefined ? undefined : (useFormData ? body : JSON.stringify(body)),
            credentials: 'same-origin',
            signal: controller?.signal,
        });

        const json = await res.json().catch(() => null);

        if (!res.ok) {
            const err = new Error(json?.message ?? `HTTP ${res.status}`);
            err.errors = json?.errors ?? {};
            err.status = res.status;
            throw err;
        }
        return json;
    } catch (error) {
        if (error?.name === 'AbortError') {
            const err = new Error(timeoutMessage || `通信がタイムアウトしました: ${method} ${path}`);
            err.code = 'timeout';
            throw err;
        }
        throw error;
    } finally {
        if (timeoutId !== null) {
            window.clearTimeout(timeoutId);
        }
    }
}

export const api = {
    get:    (path, options)         => request('GET',    path, null, false, options),
    post:   (path, body, options)   => request('POST',   path, body, false, options),
    put:    (path, body, options)   => request('PUT',    path, body, false, options),
    patch:  (path, body, options)   => request('PATCH',  path, body, false, options),
    delete: (path, options)         => request('DELETE', path, null, false, options),
    upload: (path, form, options)   => request('POST',   path, form, true, options),
    uploadPut: (path, form, options) => {
        if (form instanceof FormData && !form.has('_method')) {
            form.append('_method', 'PUT');
        }
        return request('POST', path, form, true, options);
    },
    // PATCH をマルチパートで送る（PHP は PATCH のマルチパートを解釈しないため POST + _method=PATCH）
    uploadPatch: (path, form, options) => {
        if (form instanceof FormData && !form.has('_method')) {
            form.append('_method', 'PATCH');
        }
        return request('POST', path, form, true, options);
    },
};
