// Llamadas a la API de la tienda (JSON). Los errores 422 vuelven como { errores: { campo: [mensajes] } }

function xsrf() {
    const c = document.cookie.split('; ').find((p) => p.startsWith('XSRF-TOKEN='));
    return c ? decodeURIComponent(c.split('=')[1]) : '';
}

export class ErrorApi extends Error {
    constructor(mensaje, errores = {}, estado = 0) {
        super(mensaje);
        this.errores = errores;
        this.estado = estado;
    }
}

async function pedir(url, opciones = {}) {
    let r;
    try {
        r = await fetch(url, {
            credentials: 'same-origin',
            ...opciones,
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(opciones.headers || {}) },
        });
    } catch {
        throw new ErrorApi('Sin conexión. Revisa tu internet e inténtalo de nuevo.');
    }
    const datos = await r.json().catch(() => ({}));
    if (!r.ok) {
        const errores = datos.errors || {};
        const primero = Object.values(errores).flat()[0];
        throw new ErrorApi(primero || datos.message || 'Ocurrió un error. Inténtalo de nuevo.', errores, r.status);
    }
    return datos;
}

export const obtener = (url) => pedir(url);

export const enviar = (url, cuerpo) => pedir(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrf() },
    body: JSON.stringify(cuerpo),
});
