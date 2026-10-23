import { computed, reactive, watch } from 'vue';

/**
 * Carrito en el navegador (localStorage por tienda). Solo guarda id y cantidad: precio, nombre y stock se toman
 * siempre de los datos frescos de la tienda (y el servidor vuelve a calcular todo al recibir el pedido).
 */
export function usarCarrito(slug, productos) {
    const clave = `carrito:${slug}`;
    let guardado = {};
    try {
        guardado = JSON.parse(localStorage.getItem(clave) || '{}') || {};
    } catch {
        guardado = {};
    }
    const cantidades = reactive(guardado);

    watch(cantidades, () => {
        try {
            localStorage.setItem(clave, JSON.stringify(cantidades));
        } catch {
            // navegación privada o almacenamiento lleno: el carrito sigue en memoria
        }
    }, { deep: true });

    const porId = computed(() => Object.fromEntries((productos.value || []).map((p) => [p.id, p])));

    // Líneas válidas: productos que siguen publicados, sin pasar del stock
    const lineas = computed(() => Object.entries(cantidades)
        .map(([id, cantidad]) => ({ producto: porId.value[id], cantidad }))
        .filter((l) => l.producto && !l.producto.agotado && l.cantidad > 0)
        .map((l) => ({ ...l, cantidad: l.producto.maximo != null ? Math.min(l.cantidad, l.producto.maximo) : l.cantidad }))
        .map((l) => ({ ...l, total: Math.round(l.producto.precio * l.cantidad * 100) / 100 })));

    const unidades = computed(() => lineas.value.reduce((s, l) => s + l.cantidad, 0));
    const subtotal = computed(() => Math.round(lineas.value.reduce((s, l) => s + l.total, 0) * 100) / 100);

    function poner(id, cantidad) {
        const p = porId.value[id];
        let c = Math.max(0, Math.floor(cantidad));
        if (p?.maximo != null) c = Math.min(c, Math.floor(p.maximo));
        if (c === 0) delete cantidades[id];
        else cantidades[id] = c;
    }

    return {
        lineas,
        unidades,
        subtotal,
        cantidad: (id) => cantidades[id] || 0,
        agregar: (id, n = 1) => poner(id, (cantidades[id] || 0) + n),
        poner,
        quitar: (id) => delete cantidades[id],
        vaciar: () => Object.keys(cantidades).forEach((id) => delete cantidades[id]),
    };
}

export const soles = (n) => new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(n || 0);
