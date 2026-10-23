@php
    $corrLista = collect($correlativos ?? []);
    $almLista  = collect($almacenes ?? []);
    $corrSel   = (string) old('correlativo_id', data_get(
        ($venta ? $corrLista->first(fn ($c) => $c['tipo'] === $venta->tipo) : null) ?? $corrLista->first(), 'id'));
    $almSel    = (string) old('almacen_id', $venta?->almacen_id
        ?? data_get($almLista->first(fn ($a) => data_get($a, 'es_principal')) ?? $almLista->first(), 'id'));
@endphp

<x-card title="Detalle de venta" icon="file-text" :class="$class ?? ''">
    <div class="grid gap-4 sm:grid-cols-2">
        {{-- Comprobante = fila de correlativos (tipo + serie) --}}
        <x-select name="correlativo_id" label="Comprobante">
            @forelse ($corrLista as $c)
                <option value="{{ $c['id'] }}" @selected($corrSel === (string) $c['id'])>
                    {{ $c['destipo'] ?: ucfirst($c['tipo']) }} · {{ $c['serie'] }}
                </option>
            @empty
                <option value="">Sin comprobantes configurados</option>
            @endforelse
        </x-select>

        <div>
            <label for="fecha_emision" class="field-label">Fecha de emisión</label>
            <input id="fecha_emision" name="fecha_emision" type="date" value="{{ old('fecha_emision', ($venta?->fecha_emision ?? $fecha)->format('Y-m-d')) }}" class="field">
        </div>

        {{-- Tipo, serie y siguiente número del comprobante elegido --}}
        <p class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600 sm:col-span-2">
            Tipo <b id="comprobante-tipo" class="text-ink">—</b>
            · Serie <b id="comprobante-serie" class="text-ink">—</b>
            · Próx. N.º <b id="comprobante-siguiente" class="text-ink">—</b>
            <span id="comprobante-ruc" hidden class="font-medium text-amber-700"> · Requiere cliente con RUC</span>
        </p>

        <x-select name="almacen_id" label="Almacén">
            @forelse ($almLista as $a)
                <option value="{{ data_get($a, 'id') }}" @selected($almSel === (string) data_get($a, 'id'))>{{ data_get($a, 'nombre') }}</option>
            @empty
                <option value="">Sin almacenes</option>
            @endforelse
        </x-select>

        <x-select name="metodo_pago" label="Método de pago">
            @foreach ($metodosPago as $valor => $etiqueta)
                <option value="{{ $valor }}" @selected(old('metodo_pago', $venta?->metodo_pago ?? 'efectivo') === $valor)>{{ $etiqueta }}</option>
            @endforeach
        </x-select>

        <div class="sm:col-span-2">
            <label for="observaciones" class="field-label">Observaciones (opcional)</label>
            <input id="observaciones" name="observaciones" type="text" maxlength="1000" value="{{ old('observaciones', $venta?->observaciones) }}" placeholder="Ej.: Cliente recoge en tienda." class="field">
        </div>
    </div>
</x-card>
