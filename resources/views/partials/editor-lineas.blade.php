{{--
    Editor de líneas de producto para cotizaciones (modo "precio") y órdenes de compra (modo "costo").
      $productos  [['id','nombre','codigo','precio','unidad'], ...]   precio = de venta (cotización) o costo sugerido (orden)
      $iniciales  [['id','cantidad','costo'?], ...]
      $modo       precio: precio del catálogo (no editable), descuento global (#descuento) e IGV según la empresa
                  costo:  costo unitario sin IGV editable; IGV solo si #afecto_igv está marcado
      $igv, $incluyeIgv (modo precio)
    Totales: pinta #t-bruto, #t-descuento, #t-base, #t-igv, #t-total si existen en la página.
--}}
@php $modo ??= 'precio'; $incluyeIgv ??= true; @endphp

<x-card title="Productos" icon="package" :subtitle="$modo === 'precio' ? 'Precios del catálogo; al convertir en venta se usan los precios vigentes.' : 'Costos unitarios sin IGV.'">
    <div>
        <label for="buscar-producto" class="sr-only">Buscar producto</label>
        <input id="buscar-producto" type="search" list="lista-productos" autocomplete="off" class="field" placeholder="Escribe el nombre o código y elige el producto...">
        <datalist id="lista-productos">
            @foreach ($productos as $p)
                <option value="{{ $p['nombre'] }}{{ $p['codigo'] ? ' · ' . $p['codigo'] : '' }}"></option>
            @endforeach
        </datalist>
        @error('productos')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="mt-4 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-left text-xs text-slate-500">
                <tr>
                    <th class="py-2 pr-3">Producto</th>
                    <th class="w-28 px-3 py-2 text-right">Cantidad</th>
                    <th class="w-32 px-3 py-2 text-right">{{ $modo === 'precio' ? 'Precio' : 'Costo s/ IGV' }}</th>
                    <th class="w-28 px-3 py-2 text-right">Importe</th>
                    <th class="w-10 py-2 pl-3"><span class="sr-only">Quitar</span></th>
                </tr>
            </thead>
            <tbody id="lineas" class="divide-y divide-slate-100"></tbody>
        </table>
        <p id="lineas-vacio" class="py-6 text-center text-sm text-slate-500">Aún no agregas productos.</p>
    </div>
</x-card>

@push('scripts')
<script>
(() => {
    'use strict';
    const MODO = @json($modo);
    const IGV = @json((float) ($igv ?? 18));
    const INCLUYE = @json((bool) $incluyeIgv);
    const PRODUCTOS = @json(collect($productos)->values());
    const INICIALES = @json(collect($iniciales ?? [])->values());

    const $ = (s) => document.querySelector(s);
    const nf = new Intl.NumberFormat('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const r2 = (n) => Math.round((n + Number.EPSILON) * 100) / 100;
    const num = (v) => { const n = parseFloat(v); return Number.isFinite(n) ? n : 0; };
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const etiqueta = (p) => p.nombre + (p.codigo ? ' · ' + p.codigo : '');
    const porId = new Map(PRODUCTOS.map((p) => [String(p.id), p]));
    const tbody = $('#lineas');

    function agregar(p, cantidad = 1, costo = null) {
        const existente = tbody.querySelector(`tr[data-id="${p.id}"]`);
        if (existente) {
            const c = existente.querySelector('[data-cantidad]');
            c.value = r2(num(c.value) + cantidad);
            return recalcular();
        }
        const tr = document.createElement('tr');
        tr.dataset.id = p.id;
        const precio = MODO === 'precio'
            ? `<span data-precio="${p.precio}">${nf.format(p.precio)}</span>`
            : `<input type="number" step="0.000001" min="0" data-costo value="${costo ?? p.precio}" class="field !py-1.5 text-right" aria-label="Costo de ${esc(p.nombre)}">`;
        tr.innerHTML = `
            <td class="py-2 pr-3"><input type="hidden" data-pid value="${p.id}"><p class="font-medium">${esc(p.nombre)}</p><p class="text-xs text-slate-500">${esc(p.codigo || '')}${p.unidad ? ' · ' + esc(p.unidad) : ''}</p></td>
            <td class="px-3 py-2"><input type="number" step="0.01" min="0.01" data-cantidad value="${cantidad}" class="field !py-1.5 text-right" aria-label="Cantidad de ${esc(p.nombre)}"></td>
            <td class="px-3 py-2 text-right">${precio}</td>
            <td class="px-3 py-2 text-right font-medium" data-importe></td>
            <td class="py-2 pl-3 text-right"><button type="button" data-quitar class="rounded p-1 text-slate-400 hover:bg-red-50 hover:text-red-600" aria-label="Quitar ${esc(p.nombre)}">✕</button></td>`;
        tbody.append(tr);
        recalcular();
    }

    function recalcular() {
        const filas = [...tbody.rows];
        let bruto = 0;
        filas.forEach((tr, i) => {
            tr.querySelector('[data-pid]').name = `productos[${i}][id]`;
            const cant = tr.querySelector('[data-cantidad]');
            cant.name = `productos[${i}][cantidad]`;
            const costo = tr.querySelector('[data-costo]');
            if (costo) costo.name = `productos[${i}][costo]`;
            const unit = costo ? num(costo.value) : num(tr.querySelector('[data-precio]').dataset.precio);
            const imp = r2(unit * num(cant.value));
            tr.querySelector('[data-importe]').textContent = nf.format(imp);
            bruto += imp;
        });
        $('#lineas-vacio').hidden = filas.length > 0;

        bruto = r2(bruto);
        let desc = 0, base, igv, total;
        if (MODO === 'precio') {
            const pct = Math.min(Math.max(num($('#descuento')?.value), 0), 100);
            desc = r2(bruto * pct / 100);
            const neto = r2(bruto - desc);
            if (INCLUYE) { total = neto; base = r2(total / (1 + IGV / 100)); igv = r2(total - base); }
            else { base = neto; igv = r2(base * IGV / 100); total = r2(base + igv); }
        } else {
            base = bruto;
            igv = $('#afecto_igv')?.checked ? r2(base * IGV / 100) : 0;
            total = r2(base + igv);
        }
        const pintar = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = 'S/ ' + nf.format(v); };
        pintar('t-bruto', bruto); pintar('t-descuento', desc); pintar('t-base', base); pintar('t-igv', igv); pintar('t-total', total);
    }

    const buscador = $('#buscar-producto');
    const elegir = () => {
        const v = buscador.value.trim();
        const p = PRODUCTOS.find((x) => etiqueta(x) === v);
        if (p) { agregar(p); buscador.value = ''; }
    };
    buscador.addEventListener('change', elegir);
    buscador.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); elegir(); } });

    tbody.addEventListener('input', recalcular);
    tbody.addEventListener('click', (e) => { if (e.target.closest('[data-quitar]')) { e.target.closest('tr').remove(); recalcular(); } });
    ['descuento', 'afecto_igv'].forEach((id) => document.getElementById(id)?.addEventListener('input', recalcular));
    document.getElementById('afecto_igv')?.addEventListener('change', recalcular);

    INICIALES.forEach((l) => { const p = porId.get(String(l.id)); if (p) agregar(p, num(l.cantidad) || 1, l.costo ?? null); });
    recalcular();
})();
</script>
@endpush
