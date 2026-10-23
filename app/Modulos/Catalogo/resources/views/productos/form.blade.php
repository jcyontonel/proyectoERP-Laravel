{{--
    Crear y editar producto (ProductoController@create / @edit)
      $producto     Producto (nuevo o existente, con conversiones.unidadOrigen)
      $empresa      Empresa (controlar_stock, precios_incluyen_igv, igv)
      $categorias, $tiposUnidad, $impuestos, $almacenes
      $stocks       AlmacenProducto por almacen_id (edición)
      $bloqueaBase  bool: con movimientos no se cambia la unidad base ni "es servicio"
--}}
@extends('layouts.app')

@php
    $editando = $producto->exists;
    $titulo   = $editando ? 'Editar producto' : 'Nuevo producto';

    $presentaciones = old('conversiones', $producto->conversiones->map(fn ($c) => [
        'unidad_id'    => $c->unidad_origen_id,
        'factor'       => (float) $c->factor,
        'precio_venta' => $c->precio_venta,
        'activo'       => $c->activo,
    ])->values()->all());

    $esServicio = (bool) old('es_servicio', $producto->es_servicio);
@endphp

@section('title', $titulo)

@section('content')
    <x-page-header :title="$titulo"
        :migas="array_filter(['Productos' => route('productos.index'), ($editando ? $producto->nombre : null) => $editando ? route('productos.show', $producto) : null, $titulo => null], fn ($v, $k) => $k !== '', ARRAY_FILTER_USE_BOTH)"
        :subtitle="$editando ? 'Los cambios de precio aplican a las ventas nuevas; el stock se mueve con compras, ventas y ajustes.' : 'Registra un producto o servicio de tu catálogo.'" />

    <x-flash />

    <form method="POST" action="{{ $editando ? route('productos.update', $producto) : route('productos.store') }}" data-form-producto
        class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem] xl:grid-cols-[minmax(0,1fr)_22rem]">
        @csrf
        @if ($editando) @method('PUT') @endif

        <div class="space-y-5">
            {{-- Datos generales --}}
            <x-card title="Datos generales" icon="package">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <x-input name="nombre" label="Nombre *" :value="$producto->nombre" required maxlength="255" placeholder="Ej. Ricocan Adulto Pollo 15 kg" autofocus />
                    </div>
                    <x-input name="codigo" label="Código / código de barras" :value="$producto->codigo" maxlength="50" placeholder="Ej. 7750243051234" hint="Único en tu empresa. Sirve para buscar o escanear." />
                    <x-select name="categoria_id" label="Categoría">
                        <option value="">Sin categoría</option>
                        @foreach ($categorias as $c)
                            <option value="{{ $c->id }}" @selected((string) old('categoria_id', $producto->categoria_id) === (string) $c->id)>{{ $c->nombre }}</option>
                        @endforeach
                    </x-select>
                    <div class="sm:col-span-2">
                        <x-textarea name="descripcion" label="Descripción" :value="$producto->descripcion" rows="3" maxlength="2000" placeholder="Detalle que verás en la ficha y podrás imprimir en cotizaciones." />
                    </div>
                </div>
            </x-card>

            {{-- Precio --}}
            <x-card title="Precio y costo" icon="dollar-sign"
                :subtitle="$empresa->precios_incluyen_igv ? 'El precio de venta incluye IGV (' . rtrim(rtrim(number_format($empresa->igv, 2), '0'), '.') . '%).' : 'El precio de venta NO incluye IGV: se agrega al emitir.'">
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-input name="precio_unitario" label="Precio de venta (S/) *" type="number" step="0.01" min="0" :value="$producto->precio_unitario" required data-precio />
                    <x-input name="costo_unitario" label="Costo (S/, sin IGV)" type="number" step="0.000001" min="0" :value="$producto->costo_unitario" data-costo hint="Se actualiza con cada compra." />
                    <x-select name="impuesto_id" label="Afectación al IGV">
                        <option value="">Gravado (IGV de la empresa)</option>
                        @foreach ($impuestos->where('codigo', '!=', 'IGV') as $i)
                            <option value="{{ $i->id }}" @selected((string) old('impuesto_id', $producto->impuesto_id) === (string) $i->id)>{{ $i->nombre }}</option>
                        @endforeach
                    </x-select>
                </div>
                <p class="mt-3 flex items-center gap-2 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
                    <x-icon name="trending-up" class="size-3.5 text-slate-400" />
                    Margen bruto estimado: <strong data-margen class="text-ink">—</strong>
                </p>
            </x-card>

            {{-- Unidad y presentaciones --}}
            <x-card title="Unidad y presentaciones" icon="ruler" subtitle="La unidad base es en la que se guarda el stock. Las presentaciones convierten a ella (1 Saco = 15 Kilogramo)." data-solo-producto :class="$esServicio ? 'hidden' : ''">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-select name="tipo_unidad_id" label="Unidad base *" :disabled="$bloqueaBase" data-unidad-base>
                            <option value="">Selecciona…</option>
                            @foreach ($tiposUnidad as $u)
                                <option value="{{ $u->id }}" @selected((string) old('tipo_unidad_id', $producto->tipo_unidad_id) === (string) $u->id)>{{ $u->descripcion }} ({{ $u->codigo }})</option>
                            @endforeach
                        </x-select>
                        @if ($bloqueaBase)
                            {{-- El select deshabilitado no se envía --}}
                            <input type="hidden" name="tipo_unidad_id" value="{{ $producto->tipo_unidad_id }}">
                            <p class="mt-1.5 text-[11px] text-slate-500">Bloqueada: el producto ya tiene movimientos de stock.</p>
                        @endif
                    </div>
                </div>

                <div class="mt-5">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium">Presentaciones</p>
                        <button type="button" data-agregar-presentacion class="text-xs font-semibold text-brand-600 hover:text-brand-700"><x-icon name="plus" class="inline size-3.5" /> Agregar presentación</button>
                    </div>
                    <p data-sin-presentaciones @class(['mt-2 rounded-lg border border-dashed border-slate-200 px-3 py-4 text-center text-xs text-slate-500', 'hidden' => count($presentaciones) > 0])>
                        Sin presentaciones: el producto se compra y vende solo en su unidad base.
                    </p>
                    <div data-presentaciones class="mt-2 space-y-2">
                        @foreach ($presentaciones as $i => $pr)
                            @include('productos.partials.fila-presentacion', ['i' => $i, 'pr' => $pr])
                        @endforeach
                    </div>
                    @error('conversiones') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </x-card>

            {{-- Inventario por almacén --}}
            <x-card title="Inventario por almacén" icon="warehouse" data-solo-producto :class="$esServicio ? 'hidden' : ''"
                :subtitle="$editando ? ($stockDirecto ? 'Actualiza aquí el stock de cada almacén y el mínimo para recibir alertas.' : 'Define el stock mínimo para recibir alertas. El stock se mueve con compras, ventas y ajustes.') : 'Stock mínimo para alertas y, si ya tienes mercadería, el stock inicial (queda registrado en el kardex).'">
                @if ($almacenes->isEmpty())
                    <x-alert tone="warning">Tu empresa no tiene almacenes activos.</x-alert>
                @else
                    <div class="-mx-4 overflow-x-auto sm:-mx-5">
                        <table class="w-full text-sm">
                            <thead class="border-y border-slate-200 bg-slate-50/70 text-left text-xs font-medium text-slate-500">
                                <tr>
                                    <th class="py-2.5 pl-4 pr-3 sm:pl-5">Almacén</th>
                                    @if ($editando)<th class="px-3 py-2.5 text-right">Stock actual</th>@endif
                                    <th class="px-3 py-2.5">Stock mínimo</th>
                                    @if (! $editando && $empresa->controlar_stock)<th class="py-2.5 pl-3 pr-4 sm:pr-5">Stock inicial</th>@endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($almacenes as $i => $a)
                                    <tr>
                                        <td class="py-2.5 pl-4 pr-3 sm:pl-5">
                                            <input type="hidden" name="almacenes[{{ $i }}][id]" value="{{ $a->id }}">
                                            <span class="font-medium">{{ $a->nombre }}</span>
                                            @if ($a->es_principal)<x-badge tone="info" class="ml-1">Principal</x-badge>@endif
                                        </td>
                                        @if ($editando && $stockDirecto && $empresa->controlar_stock && ! $producto->maneja_lotes)
                                            <td class="px-3 py-2.5 text-right">
                                                <input type="number" name="almacenes[{{ $i }}][stock]" step="0.0001" min="0" aria-label="Stock en {{ $a->nombre }}"
                                                    value="{{ old("almacenes.$i.stock", (float) ($stocks[$a->id]->stock ?? 0)) }}" class="field ml-auto h-9 w-32 text-right">
                                            </td>
                                        @elseif ($editando)
                                            <td class="px-3 py-2.5 text-right text-slate-600">{{ \App\Modulos\Catalogo\Support\Unidades::cantidad($stocks[$a->id]->stock ?? 0) }}</td>
                                        @endif
                                        <td class="px-3 py-2.5">
                                            <input type="number" name="almacenes[{{ $i }}][stock_minimo]" step="0.0001" min="0" aria-label="Stock mínimo en {{ $a->nombre }}"
                                                value="{{ old("almacenes.$i.stock_minimo", isset($stocks[$a->id]) ? (float) $stocks[$a->id]->stock_minimo : 0) }}" class="field h-9 w-32">
                                        </td>
                                        @if (! $editando && $empresa->controlar_stock)
                                            <td class="py-2.5 pl-3 pr-4 sm:pr-5">
                                                <input type="number" name="almacenes[{{ $i }}][stock_inicial]" step="0.0001" min="0" aria-label="Stock inicial en {{ $a->nombre }}"
                                                    value="{{ old("almacenes.$i.stock_inicial", 0) }}" class="field h-9 w-32">
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>

        {{-- Lateral: opciones y guardar --}}
        <aside class="space-y-5 lg:sticky lg:top-24 lg:self-start">
            <x-card title="Opciones" icon="sliders">
                <div class="space-y-4">
                    <x-toggle name="activo" label="Activo" :checked="$producto->activo" hint="Los inactivos no aparecen al vender ni comprar." />
                    <x-toggle name="es_servicio" label="Es un servicio" :checked="$producto->es_servicio" :disabled="$bloqueaBase" data-es-servicio
                        hint="Baños, cortes, consultas… No lleva stock." />
                    @if ($bloqueaBase)
                        <input type="hidden" name="es_servicio" value="{{ (int) $producto->es_servicio }}">
                    @endif
                    <x-toggle name="maneja_lotes" label="Controlar lotes y vencimiento" :checked="$producto->maneja_lotes" data-solo-producto :class="$esServicio ? 'hidden' : ''"
                        hint="Alimentos, medicinas: pide lote al comprar y avisa antes de vencer." />
                </div>
            </x-card>

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <button type="submit" class="btn btn-primary w-full"><x-icon name="save" /> {{ $editando ? 'Guardar cambios' : 'Crear producto' }}</button>
                <a href="{{ $editando ? route('productos.show', $producto) : route('productos.index') }}" class="btn btn-secondary mt-2 w-full">Cancelar</a>
            </div>
        </aside>
    </form>

    {{-- Plantilla de fila nueva (el índice __i__ se reemplaza al agregar) --}}
    <template id="tpl-presentacion">
        @include('productos.partials.fila-presentacion', ['i' => '__i__', 'pr' => ['unidad_id' => null, 'factor' => null, 'precio_venta' => null, 'activo' => true]])
    </template>
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.querySelector('[data-form-producto]');
            const lista = form.querySelector('[data-presentaciones]');
            const vacio = form.querySelector('[data-sin-presentaciones]');
            const tpl = document.getElementById('tpl-presentacion');
            let siguiente = {{ count($presentaciones) }};

            const refrescarVacio = () => vacio.classList.toggle('hidden', lista.children.length > 0);

            form.addEventListener('click', (e) => {
                if (e.target.closest('[data-agregar-presentacion]')) {
                    lista.insertAdjacentHTML('beforeend', tpl.innerHTML.replaceAll('__i__', siguiente++));
                    lista.lastElementChild.querySelector('select')?.focus();
                    refrescarVacio();
                }
                const quitar = e.target.closest('[data-quitar-presentacion]');
                if (quitar) {
                    quitar.closest('[data-fila-presentacion]').remove();
                    refrescarVacio();
                }
            });

            // Servicio: oculta unidad, presentaciones, inventario y lotes
            const servicio = form.querySelector('[data-es-servicio] input[type=checkbox]');
            servicio?.addEventListener('change', () => {
                form.querySelectorAll('[data-solo-producto]').forEach((el) => el.classList.toggle('hidden', servicio.checked));
            });

            // Margen bruto en vivo
            const precio = form.querySelector('[data-precio]');
            const costo = form.querySelector('[data-costo]');
            const margen = form.querySelector('[data-margen]');
            const incluyeIgv = @json((bool) $empresa->precios_incluyen_igv);
            const igv = @json((float) $empresa->igv) / 100;
            const calcular = () => {
                const p = parseFloat(precio.value) || 0;
                const c = parseFloat(costo.value) || 0;
                const base = incluyeIgv ? p / (1 + igv) : p;
                margen.textContent = base > 0 ? `${(((base - c) / base) * 100).toFixed(1)}% · S/ ${(base - c).toFixed(2)} por unidad` : '—';
                margen.classList.toggle('text-red-600', base > 0 && c > base);
            };
            [precio, costo].forEach((el) => el.addEventListener('input', calcular));
            calcular();
        })();
    </script>
@endpush
