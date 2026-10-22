@push('scripts')
<script>
(() => {
    'use strict';

    /* ───────────── Configuración y datos (los envía el controller) ───────────── */
    const CONFIG = {
        igv: @json((float) $igv),
        controlarStock: @json((bool) $controlarStock),
        // Las ventas de la empresa (precio_unitario) traen el IGV incluido
        ventaIncluyeIgv: @json((bool) $empresa->precios_incluyen_igv),
    };
    const PROVEEDORES = @json($jsProveedores);
    const PRODUCTOS   = @json($jsProductos);
    const OLD = {
        proveedor: @json(old('proveedor_id')),
        productos: @json(old('productos', [])),
    };

    /* ───────────── Utilidades ───────────── */
    const $ = (sel, root = document) => root.querySelector(sel);
    const form = $('#compra-form');
    const nf = new Intl.NumberFormat('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const money = (n) => 'S/ ' + nf.format(n);
    const round2 = (n) => Math.round((n + Number.EPSILON) * 100) / 100;
    const num = (v, def = 0) => { const n = parseFloat(v); return Number.isFinite(n) ? n : def; };
    const norm = (s) => String(s ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const setText = (sel, text) => { $(sel).textContent = text; };
    const cantidadTexto = (n) => String(Number(round2(n)));

    const indexar = () => {
        PROVEEDORES.forEach((p) => { p._k = norm(p.nombre + ' ' + p.doc); });
        PRODUCTOS.forEach((p) => { p._k = norm(p.nombre + ' ' + p.codigo); });
    };
    indexar();

    /* ───────────── Mensajes ───────────── */
    const errorBox = $('#form-errors');
    function mostrarErrores(mensajes) {
        const ul = document.createElement('ul');
        ul.className = 'list-disc pl-5';
        mensajes.forEach((m) => { const li = document.createElement('li'); li.textContent = m; ul.append(li); });
        errorBox.replaceChildren(ul);
        errorBox.hidden = false;
        errorBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    const ocultarErrores = () => { errorBox.hidden = true; };

    let avisoTimer;
    function avisar(mensaje) {
        const el = $('#producto-aviso');
        el.textContent = mensaje;
        el.hidden = false;
        clearTimeout(avisoTimer);
        avisoTimer = setTimeout(() => { el.hidden = true; }, 4500);
    }

    /* ───────────── Autocompletar reutilizable (proveedor y producto) ───────────── */
    function autocompletar({ input, list, clear, buscar, pintar, alElegir }) {
        let resultados = [];
        let activo = -1;

        const abrir = () => { list.hidden = false; input.setAttribute('aria-expanded', 'true'); };
        const cerrar = () => { list.hidden = true; input.setAttribute('aria-expanded', 'false'); activo = -1; };
        const resaltar = () => {
            [...list.querySelectorAll('[data-index]')].forEach((li, i) => {
                const on = i === activo;
                li.classList.toggle('bg-brand-50', on);
                li.setAttribute('aria-selected', String(on));
                if (on) li.scrollIntoView({ block: 'nearest' });
            });
        };
        const elegir = (item) => {
            const q = norm(input.value.trim());
            input.value = '';
            clear.hidden = true;
            cerrar();
            alElegir(item, q);
        };
        const actualizar = () => {
            const q = norm(input.value.trim());
            clear.hidden = input.value === '';
            if (!q) return cerrar();

            resultados = buscar(q);
            list.innerHTML = '';
            if (!resultados.length) {
                list.innerHTML = '<li class="px-3 py-3 text-sm text-slate-500">Sin resultados</li>';
                activo = -1;
                return abrir();
            }
            resultados.forEach((item, i) => {
                const li = document.createElement('li');
                li.dataset.index = i;
                li.setAttribute('role', 'option');
                li.className = 'cursor-pointer rounded-md px-3 py-2.5 text-sm hover:bg-brand-50';
                li.innerHTML = pintar(item);
                list.append(li);
            });
            activo = 0;
            resaltar();
            abrir();
        };

        input.addEventListener('input', actualizar);
        input.addEventListener('focus', () => { if (input.value) actualizar(); });
        input.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (list.hidden) actualizar();
                else { activo = Math.min(activo + 1, resultados.length - 1); resaltar(); }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activo = Math.max(activo - 1, 0);
                resaltar();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (!list.hidden && resultados[activo]) elegir(resultados[activo]);
            } else if (e.key === 'Escape') {
                cerrar();
            }
        });
        list.addEventListener('mousedown', (e) => {
            const li = e.target.closest('[data-index]');
            if (li) { e.preventDefault(); elegir(resultados[li.dataset.index]); }
        });
        document.addEventListener('click', (e) => {
            if (!input.contains(e.target) && !list.contains(e.target)) cerrar();
        });
        clear.addEventListener('click', () => { input.value = ''; actualizar(); input.focus(); });
    }

    /* ───────────── PROVEEDOR ───────────── */
    const prov = { sel: null };
    const provId = $('#proveedor_id');

    const etiquetaDoc = (p) => p.doc ? `${p.tipo_doc ? p.tipo_doc + ' ' : 'Doc. '}${p.doc}` : 'Sin documento';

    function setProveedor(p) {
        prov.sel = p;
        provId.value = p ? p.id : '';
        $('#proveedor-card').hidden = !p;
        $('#proveedor-vacio').hidden = !!p;
        $('#proveedor-cambiar').hidden = !p;
        if (!p) return;

        setText('#proveedor-iniciales', p.nombre.split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0].toUpperCase()).join(''));
        setText('#proveedor-nombre', p.nombre);
        setText('#proveedor-doc', etiquetaDoc(p));
        setText('#proveedor-persona', p.contacto ? `Contacto: ${p.contacto}` : '');
        setText('#proveedor-telefono', p.telefono || '');
        setText('#proveedor-email', p.email || '');
        $('#proveedor-contacto').hidden = !(p.contacto || p.telefono || p.email);
    }

    autocompletar({
        input: $('#proveedor-buscar'),
        list: $('#proveedor-resultados'),
        clear: $('#proveedor-limpiar'),
        buscar: (q) => PROVEEDORES
            .filter((p) => p._k.includes(q))
            .sort((a, b) => (norm(b.doc) === q) - (norm(a.doc) === q))
            .slice(0, 30),
        pintar: (p) => `<div class="flex items-center justify-between gap-3">
                <span class="truncate font-medium">${esc(p.nombre)}</span>
                <span class="shrink-0 text-xs text-slate-500">${esc(etiquetaDoc(p))}</span>
            </div>`,
        alElegir: (p) => { setProveedor(p); ocultarErrores(); },
    });

    $('#proveedor-cambiar').addEventListener('click', () => { setProveedor(null); $('#proveedor-buscar').focus(); });

    /* ───────────── Alta rápida de proveedor (modal + fetch JSON) ───────────── */
    const modal = $('#modal-proveedor');
    const formProv = $('#form-proveedor');
    const erroresProv = $('#np-errores');

    $('#proveedor-nuevo').addEventListener('click', () => {
        formProv.reset();
        erroresProv.hidden = true;
        modal.showModal();
        $('#np_numero').focus();
    });

    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-cerrar-proveedor]') || e.target === modal) modal.close();
    });

    formProv.addEventListener('submit', async (e) => {
        e.preventDefault();
        erroresProv.hidden = true;
        const boton = $('#np-guardar');
        boton.disabled = true;

        try {
            const res = await fetch(formProv.action, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(formProv),
            });
            const data = await res.json().catch(() => ({}));

            if (res.status === 422) {
                const mensajes = Object.values(data.errors || {}).flat();
                erroresProv.innerHTML = mensajes.map((m) => `<p>${esc(m)}</p>`).join('') || '<p>Revisa los datos.</p>';
                erroresProv.hidden = false;
            } else if (!res.ok) {
                erroresProv.innerHTML = '<p>No se pudo guardar el proveedor. Intenta de nuevo.</p>';
                erroresProv.hidden = false;
            } else {
                data._k = norm(data.nombre + ' ' + data.doc);
                PROVEEDORES.push(data);
                setProveedor(data);
                ocultarErrores();
                modal.close();
            }
        } catch {
            erroresProv.innerHTML = '<p>Sin conexión con el servidor. Intenta de nuevo.</p>';
            erroresProv.hidden = false;
        } finally {
            boton.disabled = false;
        }
    });

    /* ───────────── PRODUCTOS ───────────── */
    // item: { key, id, nombre, codigo, unidad, es_servicio, lotes, stocks, costo (actual, sin IGV), precio (venta actual),
    //         ultima, costoNuevo, cantidad, precioVenta, lote, vence }
    const items = [];
    let llave = 0;
    let seleccion = null;
    const preQty = $('#producto-cantidad [data-qty-input]');
    const preCosto = $('#sel-costo');
    const prePrecio = $('#sel-precio');
    const buscarProducto = $('#producto-buscar');
    const selAlmacen = $('#almacen_id');
    const pct = (n) => `${n > 0 ? '+' : ''}${n.toFixed(1)}%`;

    const stockDe = (p) => (p.es_servicio || !CONFIG.controlarStock) ? null : num(p.stocks?.[selAlmacen.value], 0);

    /* ── Cálculos de costo y margen (todo comparado SIN IGV) ── */
    const afecto = () => $('#afecto_igv').checked;
    const incluye = () => $('#precios_incluyen_igv').checked;
    // Costo ingresado → costo neto (sin IGV), que es como se guarda en el producto y el kardex
    const costoNeto = (c) => (afecto() && incluye()) ? c / (1 + CONFIG.igv / 100) : c;
    // Precio de venta → neto (las ventas pueden tener el IGV incluido)
    const precioNeto = (p) => CONFIG.ventaIncluyeIgv ? p / (1 + CONFIG.igv / 100) : p;
    const margen = (precio, costoSinIgv) => precioNeto(precio) > 0 ? (precioNeto(precio) - costoSinIgv) / precioNeto(precio) * 100 : null;
    const variacion = (p, costoIngresado) => p.costo > 0 ? (costoNeto(costoIngresado) - p.costo) / p.costo * 100 : null;
    // Precio que conserva el margen que tenía el producto, redondeado hacia arriba a 10 céntimos
    function precioSugerido(p, costoIngresado) {
        const m = margen(p.precio, p.costo);
        if (m === null || m >= 100) return p.precio;
        let neto = costoNeto(costoIngresado) / (1 - m / 100);
        const bruto = CONFIG.ventaIncluyeIgv ? neto * (1 + CONFIG.igv / 100) : neto;
        return Math.ceil(round2(bruto) * 10) / 10;
    }

    const tonoMargen = (m) => m === null ? 'text-slate-500' : (m < 0 ? 'text-red-600' : (m < 10 ? 'text-amber-700' : 'text-emerald-700'));
    const textoMargen = (m) => m === null ? 'Sin precio' : `Margen ${m.toFixed(1)}%`;
    const textoVariacion = (v) => v === null ? 'Primera compra'
        : (Math.abs(v) < 0.05 ? 'Igual al costo actual' : `${v > 0 ? 'Sube' : 'Baja'} ${pct(v)} vs. costo actual`);
    const tonoVariacion = (v) => v === null || Math.abs(v) < 0.05 ? 'text-slate-500' : (v > 0 ? 'text-red-600' : 'text-emerald-700');

    function clampQty(input) {
        let n = round2(num(input.value, 1));
        if (n < 0.01) n = 1;
        input.value = cantidadTexto(n);
        return n;
    }

    function cerrarSeleccion() {
        seleccion = null;
        $('#producto-seleccion').hidden = true;
        $('#producto-aviso').hidden = true;
    }
    function limpiarSeleccion() { cerrarSeleccion(); buscarProducto.focus(); }

    // Análisis en vivo del panel de selección
    function analizarSeleccion() {
        if (!seleccion) return;
        const c = num(preCosto.value, NaN);
        const precio = num(prePrecio.value, seleccion.precio);
        const partes = [];
        if (Number.isFinite(c)) {
            const v = variacion(seleccion, c);
            partes.push(`<span class="${tonoVariacion(v)} font-medium">${esc(textoVariacion(v))}</span>`);
            const m = margen(precio, costoNeto(c));
            partes.push(`<span class="${tonoMargen(m)} font-medium">${esc(textoMargen(m))} con este costo</span>`);
            if (m !== null && m < 0) partes.push('<span class="font-semibold text-red-600">¡El costo supera el precio de venta!</span>');
            if (Math.abs(precio - seleccion.precio) >= 0.005) partes.push(`<span class="rounded bg-amber-50 px-1.5 py-0.5 font-medium text-amber-700">El precio cambiará de ${money(seleccion.precio)} a ${money(precio)}</span>`);
            if (!seleccion.es_servicio && seleccion.precio > 0) partes.push(`<button type="button" data-sugerir-sel class="font-semibold text-brand-600 hover:text-brand-700">Mantener margen (${money(precioSugerido(seleccion, c))})</button>`);
        }
        $('#sel-analisis').innerHTML = partes.join('<span class="text-slate-300">·</span>');
    }

    function seleccionar(p) {
        seleccion = p;
        const st = stockDe(p);
        setText('#sel-nombre', p.nombre);
        setText('#sel-detalle', [p.codigo, p.unidad ? `Unidad: ${p.unidad}` : '', p.es_servicio ? 'Servicio (sin stock)' : '', p.lotes ? 'Controla lotes' : ''].filter(Boolean).join(' · '));
        setText('#sel-stock', st === null ? '—' : `${cantidadTexto(st)} ${p.unidad || ''}`.trim());
        setText('#sel-costo-actual', p.costo ? money(p.costo) : 'Sin costo');
        setText('#sel-precio-actual', money(p.precio));
        const m = margen(p.precio, p.costo);
        $('#sel-margen-actual').className = `mt-0.5 font-semibold ${tonoMargen(m)}`;
        setText('#sel-margen-actual', m === null || !p.costo ? '—' : `${m.toFixed(1)}%`);
        setText('#sel-ultima', p.ultima
            ? `Última compra: ${p.ultima.fecha} a ${p.ultima.proveedor ?? '—'} por ${money(p.ultima.costo)} c/u`
            : 'Es la primera vez que compras este producto.');
        setText('#sel-costo-nota', (afecto() && incluye()) ? '(con IGV)' : '(sin IGV)');

        // Costo sugerido: el de la última compra en el mismo formato que se está ingresando
        const sugerido = p.costo ? ((afecto() && incluye()) ? p.costo * (1 + CONFIG.igv / 100) : p.costo) : 0;
        preCosto.value = sugerido ? round2(sugerido).toFixed(2) : '';
        prePrecio.value = p.precio.toFixed(2);
        preQty.value = 1;
        $('#producto-seleccion').hidden = false;
        $('#producto-aviso').hidden = true;
        analizarSeleccion();
        (p.costo ? preQty : preCosto).focus();
        (p.costo ? preQty : preCosto).select();
    }

    function agregar(p, cantidad, costo, precioVenta) {
        if (!(costo >= 0)) { avisar('Indica el costo de compra del producto.'); preCosto.focus(); return false; }

        // Misma línea (producto + costo, sin lote) → se suma la cantidad
        const existente = items.find((i) => String(i.id) === String(p.id) && i.costoNuevo === costo && !i.lote && !i.vence);
        if (existente) {
            existente.cantidad = round2(existente.cantidad + cantidad);
            existente.precioVenta = precioVenta;
        } else {
            items.push({ ...p, key: ++llave, costoNuevo: costo, cantidad, precioVenta, lote: '', vence: '' });
        }

        ocultarErrores();
        renderItems();
        limpiarSeleccion();
        return true;
    }

    const agregarSeleccion = () => {
        if (!seleccion) return;
        const costo = preCosto.value === '' ? NaN : round2(num(preCosto.value, NaN));
        const precio = round2(num(prePrecio.value, seleccion.precio));
        agregar(seleccion, clampQty(preQty), costo, precio);
    };

    autocompletar({
        input: buscarProducto,
        list: $('#producto-resultados'),
        clear: $('#producto-limpiar'),
        buscar: (q) => PRODUCTOS
            .filter((p) => p._k.includes(q))
            .sort((a, b) => (norm(b.codigo) === q) - (norm(a.codigo) === q))
            .slice(0, 30),
        pintar: (p) => {
            const st = stockDe(p);
            const detalle = [p.codigo, p.es_servicio ? 'Servicio' : (st === null ? '' : `Stock: ${cantidadTexto(st)} ${p.unidad || ''}`)].filter(Boolean).join(' · ');
            return `<div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate font-medium">${esc(p.nombre)}</p>
                        <p class="text-xs text-slate-500">${esc(detalle)}</p>
                    </div>
                    <div class="shrink-0 text-right text-xs leading-tight text-slate-500">
                        <p>Costo <strong class="text-ink">${money(p.costo)}</strong></p>
                        <p>Venta <strong class="text-ink">${money(p.precio)}</strong></p>
                    </div>
                </div>`;
        },
        alElegir: (p) => seleccionar(p),
    });

    $('#producto-agregar').addEventListener('click', agregarSeleccion);
    $('#producto-cerrar').addEventListener('click', limpiarSeleccion);
    [preCosto, prePrecio].forEach((el) => el.addEventListener('input', analizarSeleccion));
    [preQty, preCosto, prePrecio].forEach((el) => el.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); agregarSeleccion(); }
        if (e.key === 'Escape') limpiarSeleccion();
    }));
    $('#sel-analisis').addEventListener('click', (e) => {
        if (!e.target.closest('[data-sugerir-sel]') || !seleccion) return;
        prePrecio.value = precioSugerido(seleccion, num(preCosto.value, 0)).toFixed(2);
        analizarSeleccion();
    });

    /* ── Líneas ── */
    const lista = $('#items-lista');
    const tpl = $('#tpl-item');
    const importeDe = (i) => round2(i.costoNuevo * i.cantidad);
    const cambiaPrecio = (i) => !i.es_servicio && Math.abs(i.precioVenta - i.precio) >= 0.005;

    // Variación, margen y aviso de cambio de precio de una línea
    function pintarAnalisis(li, i) {
        const v = variacion(i, i.costoNuevo);
        const elV = $('[data-variacion]', li);
        elV.textContent = textoVariacion(v);
        elV.className = tonoVariacion(v);
        const m = margen(i.precioVenta, costoNeto(i.costoNuevo));
        const elM = $('[data-margen]', li);
        elM.textContent = textoMargen(m);
        elM.className = `font-medium ${tonoMargen(m)}`;
        $('[data-precio-cambio]', li).hidden = !cambiaPrecio(i);
        $('[data-importe]', li).textContent = money(importeDe(i));
    }

    function renderItems() {
        lista.querySelectorAll('[data-item]').forEach((n) => n.remove());
        $('#items-vacio').hidden = items.length > 0;

        items.forEach((i) => {
            const li = tpl.content.firstElementChild.cloneNode(true);
            li.dataset.key = i.key;
            $('[data-nombre]', li).textContent = i.nombre;
            $('[data-codigo]', li).textContent = [i.codigo, i.unidad].filter(Boolean).join(' · ');
            $('[data-remove]', li).setAttribute('aria-label', `Quitar ${i.nombre}`);
            $('[data-costo]', li).value = i.costoNuevo.toFixed(2);
            const q = $('[data-qty-input]', li);
            q.value = cantidadTexto(i.cantidad);
            q.setAttribute('aria-label', `Cantidad de ${i.nombre}`);
            $('[data-precio-venta]', li).value = i.precioVenta.toFixed(2);
            $('[data-precio-fila]', li).hidden = false;
            $('[data-sugerir]', li).hidden = i.es_servicio || !(i.precio > 0);
            const lote = $('[data-lote]', li);
            lote.value = i.lote || '';
            lote.placeholder = i.lotes ? 'Lote (obligatorio)' : 'Lote (opcional)';
            lote.required = !!i.lotes;
            $('[data-vence]', li).value = i.vence || '';
            $('[data-lote-fila]', li).hidden = !!i.es_servicio;
            pintarAnalisis(li, i);
            lista.append(li);
        });
        refrescar();
    }

    const filaDe = (el) => {
        const li = el.closest('[data-item]');
        return li ? { li, item: items.find((i) => String(i.key) === li.dataset.key) } : {};
    };

    // Al cambiar de almacén solo cambia el stock informativo: se cierra la selección en curso
    selAlmacen.addEventListener('change', () => { cerrarSeleccion(); });

    form.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-qty-minus], [data-qty-plus]');
        if (btn) {
            const input = btn.closest('[data-qty]').querySelector('[data-qty-input]');
            const siguiente = num(input.value, 1) + (btn.hasAttribute('data-qty-plus') ? 1 : -1);
            input.value = cantidadTexto(Math.max(siguiente, 0.01));
            input.dispatchEvent(new Event('change', { bubbles: true }));
            return;
        }
        const sugerir = e.target.closest('[data-sugerir]');
        if (sugerir) {
            const { li, item } = filaDe(sugerir);
            if (!item) return;
            item.precioVenta = precioSugerido(item, item.costoNuevo);
            $('[data-precio-venta]', li).value = item.precioVenta.toFixed(2);
            pintarAnalisis(li, item);
            refrescar();
            return;
        }
        const quitar = e.target.closest('[data-remove]');
        if (quitar) {
            const { item } = filaDe(quitar);
            const idx = items.indexOf(item);
            if (idx > -1) items.splice(idx, 1);
            renderItems();
            buscarProducto.focus();
        }
    });

    form.addEventListener('input', (e) => {
        const t = e.target;
        const { li, item } = filaDe(t);
        if (!item) return;

        if (t.matches('[data-costo]')) {
            const c = round2(num(t.value, 0));
            item.costoNuevo = c < 0 ? 0 : c;
        } else if (t.matches('[data-qty-input]')) {
            const n = round2(num(t.value, 0));
            if (n >= 0.01) item.cantidad = n;   // en vivo mientras escribe
        } else if (t.matches('[data-precio-venta]')) {
            item.precioVenta = Math.max(round2(num(t.value, item.precio)), 0);
        } else if (t.matches('[data-lote]')) {
            item.lote = t.value.trim();
            return;
        } else if (t.matches('[data-vence]')) {
            item.vence = t.value;
            return;
        }
        pintarAnalisis(li, item);
        refrescar();
    });

    form.addEventListener('change', (e) => {
        const t = e.target;
        if (!t.matches?.('[data-qty-input]')) return;
        const n = clampQty(t);
        const { li, item } = filaDe(t);
        if (item) { item.cantidad = n; pintarAnalisis(li, item); refrescar(); }
    });

    /* ───────────── TOTALES ───────────── */
    function calcular() {
        const subtotal = round2(items.reduce((s, i) => s + importeDe(i), 0));
        let base, igv, total;

        if (!afecto()) {
            base = subtotal; igv = 0; total = subtotal;
        } else if (incluye()) {
            total = subtotal;
            base = round2(total / (1 + CONFIG.igv / 100));
            igv = round2(total - base);
        } else {
            base = subtotal;
            igv = round2(base * CONFIG.igv / 100);
            total = round2(base + igv);
        }
        return { subtotal, base, igv, total, afecto: afecto(), unidades: items.reduce((s, i) => s + i.cantidad, 0) };
    }

    function syncHidden() {
        // El servidor recalcula importes e IGV: solo necesita qué, cuánto, a qué costo y el precio nuevo (si cambió)
        $('#productos-hidden').innerHTML = items.map((p, i) => `
            <input type="hidden" name="productos[${i}][id]" value="${esc(p.id)}">
            <input type="hidden" name="productos[${i}][cantidad]" value="${p.cantidad}">
            <input type="hidden" name="productos[${i}][costo]" value="${p.costoNuevo}">
            ${cambiaPrecio(p) ? `<input type="hidden" name="productos[${i}][precio_venta]" value="${p.precioVenta}">` : ''}
            <input type="hidden" name="productos[${i}][lote]" value="${esc(p.lote)}">
            <input type="hidden" name="productos[${i}][vence]" value="${esc(p.vence)}">
        `).join('');
    }

    function refrescar() {
        const t = calcular();
        setText('#res-subtotal', money(t.subtotal));
        setText('#res-base', money(t.base));
        setText('#res-igv', money(t.igv));
        setText('#res-igv-label', t.afecto ? `IGV (${CONFIG.igv} %)` : 'IGV (no afecto)');
        setText('#res-total', money(t.total));

        const n = items.length;
        setText('#productos-contador', n === 0 ? 'Sin productos' : `${n} línea${n === 1 ? '' : 's'}`);
        const u = Number(round2(t.unidades));
        setText('#unidades-total', `${u} unidad${u === 1 ? '' : 'es'} en total`);
        const cambios = items.filter(cambiaPrecio).length;
        setText('#cambios-precio', cambios ? `${cambios} precio${cambios === 1 ? '' : 's'} de venta se actualizará${cambios === 1 ? '' : 'n'}` : '');

        $('#btn-registrar').disabled = n === 0;
        $('#estado-listo').hidden = n === 0;
        $('#estado-pendiente').hidden = n !== 0;
        syncHidden();
    }

    // Cambiar cómo se ingresa el IGV cambia el costo neto de cada línea: se repinta el análisis
    ['#afecto_igv', '#precios_incluyen_igv'].forEach((sel) => $(sel).addEventListener('change', () => { renderItems(); analizarSeleccion(); }));

    // Contado: pide método y referencia. Crédito: queda por pagar.
    const condicion = $('#condicion_pago');
    const bloquePago = $('#bloque-pago');
    const pintarPago = () => {
        bloquePago.hidden = condicion.value !== 'contado';
        $('#bloque-credito').hidden = condicion.value !== 'credito';
    };
    condicion.addEventListener('change', pintarPago);

    const tipoComp = $('#tipo_comprobante');

    /* ───────────── ENVÍO Y ATAJOS ───────────── */
    let enviando = false;

    form.addEventListener('submit', (e) => {
        if (enviando) { e.preventDefault(); return; }

        const errores = [];
        if (!prov.sel) errores.push('Selecciona el proveedor.');
        if (!$('#almacen_id').value) errores.push('Selecciona el almacén que recibe la mercadería.');
        if (tipoComp.value !== 'otro') {
            if (!$('#serie').value.trim()) errores.push('Ingresa la serie del comprobante del proveedor.');
            if (!$('#numero').value.trim()) errores.push('Ingresa el número del comprobante del proveedor.');
        }
        if (!$('#fecha_emision').value) errores.push('Ingresa la fecha de emisión.');
        if (!items.length) errores.push('Agrega al menos un producto.');
        if (items.some((i) => i.vence && !i.lote)) errores.push('Si indicas vencimiento, escribe también el código de lote.');
        items.filter((i) => i.lotes && !i.lote).forEach((i) => errores.push(`«${i.nombre}» controla lotes: indica el lote y su vencimiento.`));
        if (errores.length) { e.preventDefault(); mostrarErrores(errores); return; }

        const avisos = [];
        if (items.some((i) => i.costoNuevo === 0)) avisos.push('Hay productos con costo S/ 0.00.');
        const perdida = items.filter((i) => { const m = margen(i.precioVenta, costoNeto(i.costoNuevo)); return m !== null && m < 0; });
        if (perdida.length) avisos.push(`El costo supera el precio de venta en: ${perdida.map((i) => i.nombre).join(', ')}.`);
        if (avisos.length && !confirm(`${avisos.join('\n')}\n\n¿Registrar la compra de todos modos?`)) { e.preventDefault(); return; }

        $('#serie').value = $('#serie').value.trim().toUpperCase();
        syncHidden();
        enviando = true;
        form.setAttribute('aria-busy', 'true');
        $('#btn-registrar').classList.add('opacity-60');
    });

    // Enter dentro de un campo no debe enviar el formulario por accidente
    form.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target.matches('input')) e.preventDefault();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'F2') { e.preventDefault(); $('#proveedor-buscar').focus(); }
        else if (e.key === 'F3') { e.preventDefault(); buscarProducto.focus(); }
        else if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
            e.preventDefault();
            form.requestSubmit($('#btn-registrar').disabled ? undefined : $('#btn-registrar'));
        }
    });

    $('[data-cancelar]').addEventListener('click', (e) => {
        if (items.length && !confirm('¿Descartar esta compra? Se perderán los productos agregados.')) e.preventDefault();
        else enviando = true;
    });

    window.addEventListener('beforeunload', (e) => {
        if (items.length && !enviando) { e.preventDefault(); e.returnValue = ''; }
    });

    /* ───────────── Estado inicial (incluye datos viejos tras un error de validación) ───────────── */
    pintarPago();
    setProveedor(PROVEEDORES.find((p) => String(p.id) === String(OLD.proveedor)) ?? null);

    Object.values(OLD.productos || {}).forEach((o) => {
        const p = PRODUCTOS.find((x) => String(x.id) === String(o.id));
        const cantidad = round2(num(o.cantidad, 0));
        if (p && cantidad > 0) {
            items.push({
                ...p, key: ++llave, costoNuevo: round2(num(o.costo, 0)), cantidad,
                precioVenta: round2(num(o.precio_venta, p.precio)), lote: o.lote || '', vence: o.vence || '',
            });
        }
    });
    renderItems();
})();
</script>
@endpush
