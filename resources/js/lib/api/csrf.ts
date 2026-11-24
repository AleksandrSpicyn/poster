export function csrfHeaders() {
    const token = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
        ?.slice('XSRF-TOKEN='.length);
    return { 'X-XSRF-TOKEN': decodeURIComponent(token ?? '') };
}
