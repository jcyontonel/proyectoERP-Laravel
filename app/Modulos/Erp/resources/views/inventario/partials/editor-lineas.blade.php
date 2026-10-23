{{--
    Editor de líneas de inventario (ajustes y transferencias).
      $productos        CatalogoInventario::productos()
      $iniciales        [['id','cantidad','lote','vence'], ...]
      $almacenSelector  selector del <select> de almacén cuyo stock se muestra (#almacen_id, #almacen_origen_id)
      $tipoSelector     (opcional) selector del tipo de ajuste: ingreso | egreso | conteo
    Genera inputs productos[i][id|cantidad|lote|vence] dentro de #lineas-hidden.
--}}
<x-card title="Productos" icon="package" :class="$class ?? ''">
    <x-slot:aside><span data-contador>Sin productos</span></x-slot:aside>

    <label for="linea-buscar" class="field-label">Buscar por nombre o código</label>
    <div class="relative">
        <x-icon name="search" class="pointer-events-none absolute left-3 top-3 text-slate-400" />
        <input id="linea-buscar" type="text" autocomplete="off" placeholder="Nombre o código (Enter agrega el primero)" class="field pl-9">
        <ul data-resultados hidden class="absolute inset-x-0 top-full z-20 mt-1.5 max-h-72 overflow-y-auto rounded-lg border border-slate-200 bg-white p-1 shadow-lg"></ul>
    </div>

    <div class="mt-5 hidden grid-cols-[minmax(0,1fr)_11rem_8rem_2rem] gap-x-3 border-b border-slate-200 pb-2 text-[11px] font-medium uppercase tracking-wide text-slate-500 md:grid">
        <span>Producto</span>
        <span>Lote</span>
        <span data-encabezado-cantidad class="text-right">Cantidad</span>
        <span></span>
    </div>
    <ul data-lineas class="divide-y divide-slate-100">
        <li data-vacio class="py-10 text-center text-sm text-slate-500">Agrega los productos con el buscador.</li>
    </ul>
    <div id="lineas-hidden" class="hidden"></div>
</x-card>

@push('scripts')
    <script>
        (() => {
            'use strict';
            const PRODUCTOS = @json($productos);
            const INICIALES = @json(array_values($iniciales));
            const selAlmacen = document.querySelector(@json($almacenSelector));
            const selTipo = @json($tipoSelector ?? null) ? document.querySelector(@json($tipoSelector ?? '')) : null;

            const $ = (s, r = document) => r.querySelector(s);
            const card = $('[data-lineas]').closest('section');
            const lista = $('[data-lineas]', card);
            const buscar = $('#linea-buscar');
            const resultados = $('[data-resultados]', card);
            const norm = (s) => String(s ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
            const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
            const cant = (n) => String(Math.round((Number(n) || 0) * 10000) / 10000);
            PRODUCTOS.forEach((p) => { p._k = norm(p.nombre + ' ' + p.codigo); });

            const tipo = () => selTipo?.value ?? 'transferencia';
            const esIngreso = () => tipo() === 'ingreso';
            const esConteo = () => tipo() === 'conteo';
            const almacen = () => selAlmacen?.value;
            const stockDe = (p) => Number(p.stocks?.[almacen()] ?? 0);
            const lotesConStock = (p) => (p.lotesStock || []).filter((l) => Number(l.stocks?.[almacen()] ?? 0) > 0);
            const stockLinea = (it) => it.lote && !esIngreso()
                ? Number((it.p.lotesStock || []).find((l) => l.codigo === it.lote)?.stocks?.[almacen()] ?? 0)
                : stockDe(it.p);

            const items = [];   // { key, p, cantidad, lote, vence }
            let llave = 0;

            function pintarResultados() {
                const q = norm(buscar.value.trim());
                if (!q) { resultados.hidden = true; return; }
                const encontrados = PRODUCTOS.filter((p) => p._k.includes(q)).slice(0, 25);
                resultados.innerHTML = encontrados.length
                    ? encontrados.map((p) => `<li data-id="${p.id}" class="flex cursor-pointer items-center justify-between gap-3 rounded-md px-3 py-2 text-sm hover:bg-brand-50">
                            <span class="min-w-0"><span class="block truncate font-medium">${esc(p.nombre)}</span><span class="text-xs text-slate-500">${esc([p.codigo, p.lotes ? 'Controla lotes' : ''].filter(Boolean).join(' · '))}</span></span>
                            <span class="shrink-0 text-xs text-slate-500">Stock: <strong class="text-ink">${cant(stockDe(p))}</strong> ${esc(p.unidad || '')}</span>
                        </li>`).join('')
                    : '<li class="px-3 py-3 text-sm text-slate-500">Sin resultados</li>';
                resultados.hidden = false;
            }

            function agregar(p, datos = {}) {
                items.push({ key: ++llave, p, cantidad: datos.cantidad ?? '', lote: datos.lote ?? '', vence: datos.vence ?? '' });
                buscar.value = '';
                resultados.hidden = true;
                render();
                lista.querySelector(`[data-key="${llave}"] [data-cantidad]`)?.focus();
            }

            function celdaLote(it) {
                if (esIngreso()) {
                    return `<div class="grid grid-cols-2 gap-2 md:grid-cols-1">
                            <input data-lote value="${esc(it.lote)}" maxlength="100" placeholder="${it.p.lotes ? 'Lote *' : 'Lote (opcional)'}" class="field h-8 text-xs">
                            <input data-vence type="date" value="${esc(it.vence)}" aria-label="Vencimiento" class="field h-8 text-xs">
                        </div>`;
                }
                const lotes = lotesConStock(it.p);
                if (!it.p.lotes && !lotes.length) return '<span class="text-xs text-slate-400">—</span>';
                return `<select data-lote class="field h-8 text-xs">
                        ${it.p.lotes ? '<option value="">Elige el lote…</option>' : '<option value="">Sin lote</option>'}
                        ${lotes.map((l) => `<option value="${esc(l.codigo)}" ${l.codigo === it.lote ? 'selected' : ''}>${esc(l.codigo)} · ${cant(l.stocks[almacen()])}${l.vence ? ' · vence ' + esc(l.vence) : ''}</option>`).join('')}
                    </select>`;
            }

            function infoLinea(it) {
                const st = stockLinea(it);
                const n = Number(it.cantidad);
                if (esConteo()) {
                    if (it.cantidad === '') return `Sistema: ${cant(st)}`;
                    const dif = Math.round((n - st) * 10000) / 10000;
                    const tono = dif === 0 ? 'text-slate-500' : (dif > 0 ? 'text-emerald-700' : 'text-red-600');
                    return `Sistema: ${cant(st)} · <span class="${tono} font-semibold">${dif === 0 ? 'Sin diferencia' : (dif > 0 ? '+' : '') + cant(dif)}</span>`;
                }
                if (esIngreso()) return `Stock actual: ${cant(st)} → <strong>${cant(st + (n || 0))}</strong>`;
                const queda = st - (n || 0);
                return `Disponible: ${cant(st)}${n ? ` · queda <strong class="${queda < 0 ? 'text-red-600' : ''}">${cant(queda)}</strong>` : ''}`;
            }

            function render() {
                lista.querySelectorAll('[data-key]').forEach((li) => li.remove());
                $('[data-vacio]', card).hidden = items.length > 0;
                $('[data-encabezado-cantidad]', card).textContent = esConteo() ? 'Stock contado' : 'Cantidad';

                items.forEach((it) => {
                    const li = document.createElement('li');
                    li.dataset.key = it.key;
                    li.className = 'grid grid-cols-[minmax(0,1fr)_auto] items-start gap-x-3 gap-y-2 py-3 md:grid-cols-[minmax(0,1fr)_11rem_8rem_2rem]';
                    li.innerHTML = `
                        <div class="min-w-0 leading-tight">
                            <p class="truncate text-sm font-semibold">${esc(it.p.nombre)}</p>
                            <p class="mt-0.5 text-xs text-slate-500">${esc([it.p.codigo, it.p.unidad].filter(Boolean).join(' · '))}</p>
                            <p data-info class="mt-1 text-[11px] text-slate-500">${infoLinea(it)}</p>
                        </div>
                        <button type="button" data-quitar aria-label="Quitar ${esc(it.p.nombre)}" class="col-start-2 row-start-1 grid size-8 place-items-center rounded-md text-slate-400 hover:bg-red-50 hover:text-red-600 md:col-start-4"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                        <div class="col-span-2 md:col-span-1 md:col-start-2 md:row-start-1">${celdaLote(it)}</div>
                        <input data-cantidad type="number" step="0.0001" min="0" value="${esc(it.cantidad)}" inputmode="decimal" aria-label="Cantidad de ${esc(it.p.nombre)}"
                            class="field col-span-2 h-9 text-right md:col-span-1 md:col-start-3 md:row-start-1">`;
                    lista.append(li);
                });
                sync();
            }

            function sync() {
                const n = items.length;
                $('[data-contador]', card).textContent = n ? `${n} producto${n === 1 ? '' : 's'}` : 'Sin productos';
                document.getElementById('lineas-hidden').innerHTML = items.map((it, i) => `
                    <input type="hidden" name="productos[${i}][id]" value="${it.p.id}">
                    <input type="hidden" name="productos[${i}][cantidad]" value="${esc(it.cantidad)}">
                    <input type="hidden" name="productos[${i}][lote]" value="${esc(it.lote)}">
                    <input type="hidden" name="productos[${i}][vence]" value="${esc(esIngreso() ? it.vence : '')}">`).join('');
            }

            const itemDe = (el) => items.find((i) => String(i.key) === el.closest('[data-key]')?.dataset.key);

            buscar.addEventListener('input', pintarResultados);
            buscar.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const q = norm(buscar.value.trim());
                    const p = PRODUCTOS.find((x) => norm(x.codigo) === q) ?? PRODUCTOS.find((x) => x._k.includes(q));
                    if (q && p) agregar(p);
                }
                if (e.key === 'Escape') resultados.hidden = true;
            });
            resultados.addEventListener('mousedown', (e) => {
                const li = e.target.closest('[data-id]');
                if (li) { e.preventDefault(); agregar(PRODUCTOS.find((p) => String(p.id) === li.dataset.id)); }
            });
            document.addEventListener('click', (e) => { if (!card.contains(e.target)) resultados.hidden = true; });

            lista.addEventListener('click', (e) => {
                if (!e.target.closest('[data-quitar]')) return;
                items.splice(items.indexOf(itemDe(e.target)), 1);
                render();
            });
            lista.addEventListener('input', (e) => {
                const it = itemDe(e.target);
                if (!it) return;
                if (e.target.matches('[data-cantidad]')) it.cantidad = e.target.value;
                if (e.target.matches('[data-lote]')) it.lote = e.target.value.trim();
                if (e.target.matches('[data-vence]')) it.vence = e.target.value;
                e.target.closest('[data-key]').querySelector('[data-info]').innerHTML = infoLinea(it);
                sync();
            });

            // Cambiar almacén o tipo cambia stock y lotes disponibles
            [selAlmacen, selTipo].filter(Boolean).forEach((el) => el.addEventListener('change', () => {
                if (!esIngreso()) items.forEach((it) => { if (it.lote && !lotesConStock(it.p).some((l) => l.codigo === it.lote)) it.lote = ''; });
                render();
            }));

            // Validación previa al envío (el servidor vuelve a validar todo)
            card.closest('form').addEventListener('submit', (e) => {
                const errores = [];
                if (!items.length) errores.push('Agrega al menos un producto.');
                items.forEach((it) => {
                    const n = Number(it.cantidad);
                    if (it.cantidad === '' || n < 0 || (!esConteo() && n <= 0)) errores.push(`Indica la cantidad de «${it.p.nombre}».`);
                    if (it.p.lotes && !it.lote) errores.push(`«${it.p.nombre}» controla lotes: indica el lote.`);
                    if (!esIngreso() && !esConteo() && n > stockLinea(it)) errores.push(`«${it.p.nombre}»: solo hay ${cant(stockLinea(it))} disponible.`);
                });
                if (errores.length) {
                    e.preventDefault();
                    alert(errores.join('\n'));
                    return;
                }
                sync();
            });

            INICIALES.forEach((o) => {
                const p = PRODUCTOS.find((x) => String(x.id) === String(o.id));
                if (p) items.push({ key: ++llave, p, cantidad: o.cantidad ?? '', lote: o.lote ?? '', vence: o.vence ?? '' });
            });
            render();
        })();
    </script>
@endpush
