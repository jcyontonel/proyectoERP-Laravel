{{-- FraccionamientoController@create: $reglas [id, origen, destino, unidades, factor, costo, lotes_activo, stocks, lotes], $almacenes, $reglaInicial --}}
@extends('layouts.app')

@section('title', 'Partir producto')

@section('content')
    <x-page-header title="Partir producto" :migas="['Inventario' => null, 'Fraccionamientos' => route('fraccionamientos.index'), 'Partir' => null]"
        subtitle="Ej.: abrir 1 saco de 15 kg deja 15 kg disponibles del producto «por kilo». El costo del saco se reparte entre los kilos." />

    <x-flash />

    <form method="POST" action="{{ route('fraccionamientos.store') }}" data-fraccionar class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
        @csrf

        <x-card title="¿Qué vas a partir?" icon="layers">
            <div class="space-y-4">
                <x-select name="unidad_conversion_id" label="Producto de origen → destino *" required>
                    @foreach ($reglas as $r)
                        <option value="{{ $r['id'] }}" @selected((string) old('unidad_conversion_id', $reglaInicial) === (string) $r['id'])>
                            {{ $r['origen'] }} → {{ $r['destino'] }} (1 {{ $r['unidad_origen'] }} = {{ $r['factor'] + 0 }} {{ $r['unidad_destino'] }})
                        </option>
                    @endforeach
                </x-select>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-select name="almacen_id" label="Almacén *">
                        @foreach ($almacenes as $a)
                            <option value="{{ $a->id }}" @selected((string) old('almacen_id') === (string) $a->id)>{{ $a->nombre }}</option>
                        @endforeach
                    </x-select>
                    <x-input name="cantidad" label="Cantidad a partir *" type="number" step="1" min="1" :value="old('cantidad', 1)" required data-cantidad />
                </div>

                <div data-bloque-lote hidden>
                    <x-select name="lote" label="Lote del origen *">
                        <option value="">Elige el lote…</option>
                    </x-select>
                    <p class="mt-1.5 text-[11px] text-slate-500">El producto de destino hereda el mismo lote y vencimiento.</p>
                </div>

                <x-input name="observacion" label="Observación" maxlength="500" placeholder="Opcional" />
            </div>
        </x-card>

        <aside class="space-y-5 lg:sticky lg:top-24 lg:self-start">
            <x-card title="Resultado" icon="check-circle">
                <dl class="divide-y divide-slate-100 text-sm">
                    <x-dato label="Stock del origen"><span data-stock>—</span></x-dato>
                    <x-dato label="Sale"><span data-sale class="font-semibold text-red-600">—</span></x-dato>
                    <x-dato label="Entra"><span data-entra class="font-semibold text-emerald-700">—</span></x-dato>
                    <x-dato label="Costo por unidad destino"><span data-costo>—</span></x-dato>
                </dl>
                <p data-aviso hidden class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700"></p>
                <button type="submit" class="btn btn-primary mt-4 w-full"><x-icon name="check" /> Partir y registrar</button>
                <a href="{{ route('fraccionamientos.index') }}" class="mt-3 block text-center text-xs text-slate-500 hover:text-ink">Cancelar</a>
            </x-card>
        </aside>
    </form>
@endsection

@push('scripts')
    <script>
        (() => {
            const REGLAS = @json($reglas);
            const OLD_LOTE = @json(old('lote'));
            const form = document.querySelector('[data-fraccionar]');
            const selRegla = document.getElementById('unidad_conversion_id');
            const selAlmacen = document.getElementById('almacen_id');
            const cantidad = form.querySelector('[data-cantidad]');
            const selLote = document.getElementById('lote');
            const nf = new Intl.NumberFormat('es-PE', { maximumFractionDigits: 4 });
            const money = (n) => 'S/ ' + new Intl.NumberFormat('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 4 }).format(n);
            const $ = (s) => form.querySelector(s);

            const regla = () => REGLAS.find((r) => String(r.id) === selRegla.value);

            function lotes() {
                const r = regla();
                const bloque = $('[data-bloque-lote]');
                bloque.hidden = !r?.lotes_activo;
                const opciones = (r?.lotes || []).filter((l) => String(l.almacen) === selAlmacen.value);
                selLote.innerHTML = '<option value="">Elige el lote…</option>' + opciones.map((l) =>
                    `<option value="${l.codigo}" data-cantidad="${l.cantidad}" ${l.codigo === OLD_LOTE ? 'selected' : ''}>${l.codigo} · ${nf.format(l.cantidad)}${l.vence ? ' · vence ' + l.vence : ''}</option>`).join('');
                selLote.required = !!r?.lotes_activo;
            }

            function pintar() {
                const r = regla();
                if (!r) return;
                const n = parseFloat(cantidad.value) || 0;
                const stockLote = r.lotes_activo ? parseFloat(selLote.selectedOptions[0]?.dataset.cantidad || 0) : null;
                const stock = stockLote ?? Number(r.stocks?.[selAlmacen.value] ?? 0);
                $('[data-stock]').textContent = `${nf.format(stock)} ${r.unidad_origen}`;
                $('[data-sale]').textContent = `−${nf.format(n)} ${r.unidad_origen} de ${r.origen}`;
                $('[data-entra]').textContent = `+${nf.format(n * r.factor)} ${r.unidad_destino} de ${r.destino}`;
                $('[data-costo]').textContent = r.factor > 0 ? money(r.costo / r.factor) : '—';
                const aviso = $('[data-aviso]');
                aviso.hidden = n <= stock;
                aviso.textContent = n > stock ? `Solo hay ${nf.format(stock)} ${r.unidad_origen} para partir en este almacén.` : '';
            }

            selRegla.addEventListener('change', () => { lotes(); pintar(); });
            selAlmacen.addEventListener('change', () => { lotes(); pintar(); });
            [cantidad, selLote].forEach((el) => el.addEventListener('input', pintar));
            lotes();
            pintar();
        })();
    </script>
@endpush
