@push('scripts')
<script>
(() => {
    'use strict';

    /* ───────────── Configuración y datos (los envía el controller) ───────────── */
    const CONFIG = {
        igv: @json((float) $igv),
        preciosIncluyenIgv: @json((bool) $preciosIncluyenIgv),
        controlarStock: @json((bool) $controlarStock),
    };
    const CLIENTES     = @json($jsClientes);
    const PRODUCTOS    = @json($jsProductos);
    const CORRELATIVOS = @json($jsCorrelativos);
    const OLD = {
        cliente: @json(old('cliente_id', $venta?->cliente_id)),
        productos: @json($productosIniciales),
    };

    /* ───────────── Utilidades ───────────── */
    const $ = (sel, root = document) => root.querySelector(sel);
    const form = $('#venta-form');
    const nf = new Intl.NumberFormat('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const money = (n) => 'S/ ' + nf.format(n);
    const round2 = (n) => Math.round((n + Number.EPSILON) * 100) / 100;
    const norm = (s) => String(s ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const setText = (sel, text) => { $(sel).textContent = text; };

    CLIENTES.forEach((c) => { c._k = norm(c.nombre + ' ' + c.doc); });
    PRODUCTOS.forEach((p) => { p._k = norm(p.nombre + ' ' + p.codigo); });

    /* ───────────── Mensajes de error ───────────── */
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

    /* ───────────── Comprobante (tipo + serie + siguiente número) ───────────── */
    const selCorr = $('#correlativo_id');
    const corrActual = () => CORRELATIVOS.find((c) => String(c.id) === selCorr.value) ?? null;

    function pintarCorrelativo() {
        const c = corrActual();
        setText('#num-comprobante', c ? `${c.serie}-${c.siguiente}` : 'Sin comprobante');
        setText('#comprobante-tipo', c ? (c.destipo || c.tipo) : '—');
        setText('#comprobante-serie', c ? c.serie : '—');
        setText('#comprobante-siguiente', c ? c.siguiente : '—');
        $('#comprobante-ruc').hidden = !(c && c.requiere_ruc);
    }
    selCorr.addEventListener('change', () => { pintarCorrelativo(); ocultarErrores(); });

    /* ───────────── Autocompletar reutilizable (cliente y producto) ───────────── */
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

    /* ───────────── CLIENTE ───────────── */
    const clienteState = { sel: null };
    const clienteId = $('#cliente_id');

    const etiquetaDoc = (c) => c.doc ? `${c.tipo_doc ? c.tipo_doc + ' ' : 'Doc. '}${c.doc}` : 'Sin documento';

    function setCliente(c) {
        clienteState.sel = c;
        clienteId.value = c ? c.id : '';
        $('#cliente-card').hidden = !c;
        $('#cliente-vacio').hidden = !!c;
        $('#cliente-cambiar').hidden = !c;
        if (!c) return;

        setText('#cliente-iniciales', c.nombre.split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0].toUpperCase()).join(''));
        setText('#cliente-nombre', c.nombre);
        setText('#cliente-doc', etiquetaDoc(c));
        setText('#cliente-email', c.email || '');
        setText('#cliente-telefono', c.telefono || '');
        $('#cliente-contacto').hidden = !(c.email || c.telefono);
    }

    autocompletar({
        input: $('#cliente-buscar'),
        list: $('#cliente-resultados'),
        clear: $('#cliente-limpiar'),
        buscar: (q) => CLIENTES
            .filter((c) => c._k.includes(q))
            .sort((a, b) => (norm(b.doc) === q) - (norm(a.doc) === q))
            .slice(0, 30),
        pintar: (c) => `<div class="flex items-center justify-between gap-3">
                <span class="truncate font-medium">${esc(c.nombre)}</span>
                <span class="shrink-0 text-xs text-slate-500">${esc(etiquetaDoc(c))}</span>
            </div>`,
        alElegir: (c) => { setCliente(c); ocultarErrores(); },
    });

    $('#cliente-cambiar').addEventListener('click', () => { setCliente(null); $('#cliente-buscar').focus(); });

    /* ── DNI (8) o RUC (11) que no está en la lista: se consulta en RENIEC/SUNAT y se registra solo ──
       El navegador llama a Laravel (clientes.documento); el token de APIPeru nunca sale del servidor. */
    const URL_DOCUMENTO = @json(route('clientes.documento', ['numero' => '__NUM__']));
    const inputCliente = $('#cliente-buscar');
    const estadoCliente = $('#cliente-consulta');
    const esDocumento = (q) => /^(\d{8}|\d{11})$/.test(q);
    const fuente = (n) => n.length === 8 ? 'RENIEC' : 'SUNAT';
    const tonosConsulta = {
        cargando: 'bg-brand-50 text-brand-700',
        info:     'bg-slate-50 text-slate-600',
        ok:       'bg-emerald-50 text-emerald-700',
        error:    'bg-red-50 text-red-700',
    };
    let timerDoc;
    let consultando = null;

    function estadoConsulta(tono, texto = '') {
        estadoCliente.hidden = !texto;
        estadoCliente.className = `mt-2 flex items-center gap-2 rounded-lg px-3 py-2 text-xs ${tonosConsulta[tono] ?? ''}`;
        estadoCliente.innerHTML = tono === 'cargando'
            ? `<span class="size-3 animate-spin rounded-full border-2 border-current border-t-transparent"></span>${esc(texto)}`
            : esc(texto);
    }

    async function consultarDocumento(numero) {
        if (consultando === numero) return;
        consultando = numero;
        estadoConsulta('cargando', `Buscando ${numero.length === 8 ? 'DNI' : 'RUC'} ${numero} en ${fuente(numero)}…`);

        try {
            const res = await fetch(URL_DOCUMENTO.replace('__NUM__', numero), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await res.json().catch(() => ({}));
            if (inputCliente.value.trim() !== numero) return;   // el usuario siguió escribiendo

            if (!res.ok) {
                const mensaje = {
                    403: 'No tienes permiso para registrar clientes.',
                    429: 'Hiciste muchas consultas seguidas: espera un momento.',
                }[res.status] ?? (data.errors ? Object.values(data.errors).flat()[0] : 'No se pudo consultar el documento.');
                estadoConsulta('error', mensaje);
                return;
            }

            data._k = norm(data.nombre + ' ' + data.doc);
            if (!CLIENTES.some((c) => String(c.id) === String(data.id))) CLIENTES.push(data);
            inputCliente.value = '';
            $('#cliente-limpiar').hidden = true;
            $('#cliente-resultados').hidden = true;
            setCliente(data);
            ocultarErrores();
            estadoConsulta('ok', data.mensaje || 'Cliente seleccionado.');
        } catch {
            estadoConsulta('error', 'Sin conexión con el servidor. Intenta de nuevo.');
        } finally {
            consultando = null;
        }
    }

    inputCliente.addEventListener('input', () => {
        clearTimeout(timerDoc);
        const q = inputCliente.value.trim();
        if (!esDocumento(q) || CLIENTES.some((c) => c.doc === q)) {
            if (!q || !/^\d+$/.test(q)) estadoConsulta();
            return;   // texto, documento incompleto o ya en la lista: lo resuelve el autocompletado
        }
        $('#cliente-resultados').hidden = true;
        estadoConsulta('info', `No está en tu lista. Buscando en ${fuente(q)}…`);
        timerDoc = setTimeout(() => consultarDocumento(q), 500);
    });

    inputCliente.addEventListener('keydown', (e) => {
        const q = inputCliente.value.trim();
        if (e.key === 'Enter' && esDocumento(q) && !CLIENTES.some((c) => c.doc === q)) {
            clearTimeout(timerDoc);
            consultarDocumento(q);
        }
    });

    /* ───────────── PRODUCTOS ───────────── */
    const items = [];          // { id, nombre, codigo, precio, es_servicio, stocks, cantidad }
    let seleccion = null;      // producto elegido en el buscador (aún sin agregar)
    const preQty = $('#producto-cantidad [data-qty-input]');
    const buscarProducto = $('#producto-buscar');
    const selAlmacen = $('#almacen_id');

    // Cantidades: los productos por kilo/litro/metro (p.decimal) admiten decimales (0.001)
    const r3 = (n) => Math.round((Number(n) + Number.EPSILON) * 1000) / 1000;
    const leerCantidad = (valor, decimal) => decimal ? r3(parseFloat(valor) || 0) : parseInt(valor, 10);
    const minimo = (p) => p.decimal ? 0.001 : 1;
    const etiquetaCant = (n, p) => `${r3(n)}${p.unidad ? ' ' + p.unidad.toLowerCase() : ''}`;

    // Stock del almacén elegido + lo que se obtiene partiendo el producto de origen (ej. abrir un saco).
    // Los servicios (es_servicio) no llevan stock.
    const stockPropio = (p) => Number(p.stocks?.[selAlmacen.value] ?? 0);
    const extraFraccion = (p) => Number(p.fraccionable?.[selAlmacen.value] ?? 0);
    const stockDe = (p) => {
        if (!CONFIG.controlarStock || p.es_servicio) return Infinity;
        const total = stockPropio(p) + extraFraccion(p);
        return p.decimal ? r3(total) : Math.floor(total);
    };
    const enCarrito = (id) => items.find((i) => String(i.id) === String(id))?.cantidad ?? 0;
    const disponible = (p) => Math.max(r3(stockDe(p) - enCarrito(p.id)), 0);
    const textoStock = (p) => {
        const extra = extraFraccion(p);
        return `Stock: ${etiquetaCant(stockPropio(p), p)}${extra > 0 ? ` (+${r3(extra)} al partir)` : ''}`;
    };
    const sinStock = (p) => Number.isFinite(stockDe(p)) && stockDe(p) <= 0;

    function clampQty(input) {
        const dec = input.dataset.decimal === '1';
        const min = dec ? 0.001 : 1;
        const max = input.max ? Number(input.max) : Infinity;
        let n = leerCantidad(input.value, dec);
        if (!Number.isFinite(n) || n < min) n = dec ? Math.min(1, max) || min : 1;
        if (max >= min && n > max) { n = max; avisar(`Stock máximo disponible: ${r3(max)}.`); }
        input.value = r3(n);
        return n;
    }

    // El input de cantidad acepta decimales solo si el producto se vende fraccionado
    function prepararCantidad(input, p) {
        input.dataset.decimal = p.decimal ? '1' : '0';
        input.step = p.decimal ? '0.001' : '1';
        input.min = String(minimo(p));
        input.inputMode = p.decimal ? 'decimal' : 'numeric';
    }

    function cerrarSeleccion() {
        seleccion = null;
        $('#producto-seleccion').hidden = true;
        $('#producto-aviso').hidden = true;
    }
    function limpiarSeleccion() { cerrarSeleccion(); buscarProducto.focus(); }

    function seleccionar(p) {
        const disp = disponible(p);
        if (disp <= 0) { avisar(`«${p.nombre}» no tiene stock disponible en este almacén.`); return; }
        seleccion = p;
        setText('#sel-nombre', p.nombre);
        setText('#sel-detalle', [
            p.codigo,
            p.es_servicio ? 'Servicio' : (CONFIG.controlarStock ? textoStock(p) : ''),
            p.decimal ? 'Se vende fraccionado' : '',
        ].filter(Boolean).join(' · '));
        setText('#sel-precio', money(p.precio) + (p.unidad ? ` / ${p.unidad.toLowerCase()}` : ''));
        prepararCantidad(preQty, p);
        preQty.value = 1;
        preQty.max = Number.isFinite(disp) ? disp : '';
        $('#producto-seleccion').hidden = false;
        $('#producto-aviso').hidden = true;
        preQty.focus();
        preQty.select();
    }

    function agregar(p, cantidad) {
        const n = p.decimal ? Math.max(0.001, r3(Number(cantidad) || 0)) : Math.max(1, Math.floor(Number(cantidad) || 1));
        const disp = disponible(p);
        if (n > disp) {
            avisar(disp > 0 ? `Solo hay ${etiquetaCant(disp, p)} disponible(s) de «${p.nombre}».` : `«${p.nombre}» no tiene stock disponible en este almacén.`);
            return false;
        }
        const existente = items.find((i) => String(i.id) === String(p.id));
        if (existente) existente.cantidad = r3(existente.cantidad + n);
        else items.push({ ...p, cantidad: n });

        ocultarErrores();
        renderItems();
        limpiarSeleccion();
        return true;
    }

    autocompletar({
        input: buscarProducto,
        list: $('#producto-resultados'),
        clear: $('#producto-limpiar'),
        buscar: (q) => PRODUCTOS
            .filter((p) => p._k.includes(q))
            .sort((a, b) => (norm(b.codigo) === q) - (norm(a.codigo) === q))
            .slice(0, 30),
        pintar: (p) => {
            const agotado = sinStock(p);
            const detalle = [p.codigo, p.es_servicio ? 'Servicio' : (CONFIG.controlarStock ? textoStock(p) : '')].filter(Boolean).join(' · ');
            return `<div class="flex items-center justify-between gap-3 ${agotado ? 'opacity-60' : ''}">
                    <div class="min-w-0">
                        <p class="truncate font-medium">${esc(p.nombre)}</p>
                        <p class="text-xs ${agotado ? 'text-red-600' : 'text-slate-500'}">${esc(agotado ? 'Sin stock en este almacén' : detalle)}</p>
                    </div>
                    <span class="shrink-0 font-semibold">${money(p.precio)}</span>
                </div>`;
        },
        // Código exacto (lector de barras + Enter) → se agrega directo con cantidad 1
        alElegir: (p, q) => {
            if (q && norm(p.codigo) === q && disponible(p) > 0) agregar(p, 1);
            else seleccionar(p);
        },
    });

    $('#producto-agregar').addEventListener('click', () => { if (seleccion) agregar(seleccion, clampQty(preQty)); });
    preQty.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); if (seleccion) agregar(seleccion, clampQty(preQty)); }
        if (e.key === 'Escape') limpiarSeleccion();
    });

    /* ── Líneas ── */
    const lista = $('#items-lista');
    const tpl = $('#tpl-item');

    function renderItems() {
        lista.querySelectorAll('[data-item]').forEach((n) => n.remove());
        $('#items-vacio').hidden = items.length > 0;

        items.forEach((i) => {
            const li = tpl.content.firstElementChild.cloneNode(true);
            li.dataset.id = i.id;
            $('[data-nombre]', li).textContent = i.nombre;
            $('[data-codigo]', li).textContent = i.codigo || '';
            $('[data-precio]', li).textContent = money(i.precio);
            $('[data-importe]', li).textContent = money(round2(i.precio * i.cantidad));
            $('[data-remove]', li).setAttribute('aria-label', `Quitar ${i.nombre}`);
            const q = $('[data-qty-input]', li);
            prepararCantidad(q, i);
            q.value = r3(i.cantidad);
            q.setAttribute('aria-label', `Cantidad de ${i.nombre}`);
            const st = stockDe(i);
            if (Number.isFinite(st)) q.max = st;
            lista.append(li);
        });

        if (seleccion) {
            const d = disponible(seleccion);
            preQty.max = Number.isFinite(d) ? d : '';
        }
        refrescar();
    }

    function actualizarFila(li, input) {
        const item = items.find((i) => String(i.id) === li.dataset.id);
        if (!item) return;
        item.cantidad = leerCantidad(input.value, item.decimal) || minimo(item);
        $('[data-importe]', li).textContent = money(round2(item.precio * item.cantidad));
        refrescar();
    }

    // Al cambiar de almacén el stock cambia: se ajustan o quitan las líneas que ya no alcanzan
    function revalidarStock() {
        const avisos = [];
        for (let k = items.length - 1; k >= 0; k--) {
            const it = items[k];
            const st = stockDe(it);
            if (st <= 0) {
                avisos.push(`«${it.nombre}» no tiene stock en este almacén y se quitó de la venta.`);
                items.splice(k, 1);
            } else if (it.cantidad > st) {
                avisos.push(`«${it.nombre}»: cantidad ajustada a ${st} (stock del almacén).`);
                it.cantidad = st;
            }
        }
        cerrarSeleccion();
        renderItems();
        if (avisos.length) mostrarErrores(avisos);
    }
    selAlmacen.addEventListener('change', revalidarStock);

    // Botones − / + y quitar
    form.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-qty-minus], [data-qty-plus]');
        if (btn) {
            const input = btn.closest('[data-qty]').querySelector('[data-qty-input]');
            const dec = input.dataset.decimal === '1';
            input.value = r3((leerCantidad(input.value, dec) || 1) + (btn.hasAttribute('data-qty-plus') ? 1 : -1));
            input.dispatchEvent(new Event('change', { bubbles: true }));
            return;
        }
        const quitar = e.target.closest('[data-remove]');
        if (quitar) {
            const li = quitar.closest('[data-item]');
            const idx = items.findIndex((i) => String(i.id) === li.dataset.id);
            if (idx > -1) items.splice(idx, 1);
            renderItems();
            buscarProducto.focus();
        }
    });

    form.addEventListener('input', (e) => {
        const input = e.target.closest?.('[data-qty-input]');
        const li = input?.closest('[data-item]');
        if (!li) return;
        const dec = input.dataset.decimal === '1';
        const n = leerCantidad(input.value, dec);
        const max = input.max ? Number(input.max) : Infinity;
        if (n >= (dec ? 0.001 : 1) && n <= max) actualizarFila(li, input);   // en vivo mientras escribe
    });

    form.addEventListener('change', (e) => {
        const input = e.target.closest?.('[data-qty-input]');
        if (!input) return;
        clampQty(input);
        const li = input.closest('[data-item]');
        if (li) actualizarFila(li, input);
    });

    /* ───────────── TOTALES ───────────── */
    function calcular() {
        const subtotal = round2(items.reduce((s, i) => s + i.precio * i.cantidad, 0));
        let pct = parseFloat($('#descuento').value);
        pct = Number.isFinite(pct) ? Math.min(Math.max(pct, 0), 100) : 0;

        const descuento = round2(subtotal * pct / 100);
        const neto = round2(subtotal - descuento);

        let base, igv, total;
        if (CONFIG.preciosIncluyenIgv) {            // el precio ya trae IGV
            total = neto;
            base = round2(total / (1 + CONFIG.igv / 100));
            igv = round2(total - base);
        } else {                                    // el IGV se suma (diseño por defecto)
            base = neto;
            igv = round2(base * CONFIG.igv / 100);
            total = round2(base + igv);
        }
        return { subtotal, pct, descuento, base, igv, total, unidades: items.reduce((s, i) => s + i.cantidad, 0) };
    }

    function syncHidden() {
        // El servidor solo usa id y cantidad (precios y totales los recalcula con la base de datos)
        $('#productos-hidden').innerHTML = items.map((p, i) => `
            <input type="hidden" name="productos[${i}][id]" value="${esc(p.id)}">
            <input type="hidden" name="productos[${i}][cantidad]" value="${p.cantidad}">
        `).join('');
    }

    function refrescar() {
        const t = calcular();
        setText('#res-subtotal', money(t.subtotal));
        setText('#res-descuento-label', `Descuento aplicado (${Number(t.pct.toFixed(2))} %)`);
        setText('#res-descuento', `− ${money(t.descuento)}`);
        setText('#res-base', money(t.base));
        setText('#res-igv', money(t.igv));
        setText('#res-total', money(t.total));

        $('#h-subtotal').value = t.subtotal.toFixed(2);
        $('#h-descuento-monto').value = t.descuento.toFixed(2);
        $('#h-base').value = t.base.toFixed(2);
        $('#h-igv').value = t.igv.toFixed(2);
        $('#h-total').value = t.total.toFixed(2);

        const n = items.length;
        setText('#productos-contador', n === 0 ? 'Sin productos' : `${n} producto${n === 1 ? '' : 's'}`);
        setText("#unidades-total", `${r3(t.unidades)} unidad${t.unidades === 1 ? "" : "es"} en total`);

        $('#btn-registrar').disabled = n === 0 || !corrActual();
        $('#estado-listo').hidden = n === 0;
        $('#estado-pendiente').hidden = n !== 0;
        syncHidden();
    }

    $('#descuento').addEventListener('input', refrescar);

    /* ───────────── ENVÍO Y ATAJOS ───────────── */
    let enviando = false;

    form.addEventListener('submit', (e) => {
        if (enviando) { e.preventDefault(); return; }

        const accion = e.submitter?.value || 'registrar';
        const errores = [];
        const corr = corrActual();

        if (!corr) errores.push('Selecciona el comprobante que vas a emitir.');
        if (!items.length) errores.push('Agrega al menos un producto.');
        if (accion === 'registrar' && corr?.requiere_ruc && !clienteState.sel?.es_ruc) {
            errores.push('Para emitir una factura selecciona un cliente con RUC.');
        }
        if (errores.length) { e.preventDefault(); mostrarErrores(errores); return; }

        syncHidden();
        enviando = true;
        form.setAttribute('aria-busy', 'true');
        e.submitter?.classList.add('opacity-60');
    });

    // Enter dentro de un campo no debe enviar el formulario por accidente
    form.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target.matches('input')) e.preventDefault();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'F2') { e.preventDefault(); $('#cliente-buscar').focus(); }
        else if (e.key === 'F3') { e.preventDefault(); buscarProducto.focus(); }
        else if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
            e.preventDefault();
            const btn = $('#btn-registrar');
            form.requestSubmit(btn.disabled ? undefined : btn);
        }
    });

    $('[data-cancelar]').addEventListener('click', (e) => {
        if (items.length && !confirm('¿Descartar esta venta? Se perderán los productos agregados.')) e.preventDefault();
        else enviando = true;   // evita el aviso de beforeunload al salir
    });

    window.addEventListener('beforeunload', (e) => {
        if (items.length && !enviando) { e.preventDefault(); e.returnValue = ''; }
    });

    /* ───────────── Estado inicial (incluye datos viejos tras un error de validación) ───────────── */
    pintarCorrelativo();
    setCliente(CLIENTES.find((c) => String(c.id) === String(OLD.cliente)) ?? null);

    Object.values(OLD.productos || {}).forEach((o) => {
        const p = PRODUCTOS.find((x) => String(x.id) === String(o.id));
        const n = p ? leerCantidad(o.cantidad, p.decimal) : 0;
        if (p && n > 0) items.push({ ...p, cantidad: n });
    });
    renderItems();
})();
</script>
@endpush
