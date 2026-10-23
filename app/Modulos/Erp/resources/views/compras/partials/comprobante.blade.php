@php
    $almLista = collect($almacenes ?? []);
    $almSel   = (string) old('almacen_id', data_get($almLista->first(fn ($a) => data_get($a, 'es_principal')) ?? $almLista->first(), 'id'));
@endphp

<x-card title="Comprobante del proveedor" icon="file-text" :class="$class ?? ''">
    <div class="grid gap-4 sm:grid-cols-2">
        <x-select name="tipo_comprobante" label="Tipo de comprobante">
            @foreach ($tiposComprobante as $valor => $etiqueta)
                <option value="{{ $valor }}" @selected(old('tipo_comprobante', 'factura') === $valor)>{{ $etiqueta }}</option>
            @endforeach
        </x-select>

        <x-input name="fecha_emision" label="Fecha de emisión" type="date" :value="now()->format('Y-m-d')" max="{{ now()->format('Y-m-d') }}" />

        <x-input name="serie" label="Serie" maxlength="10" placeholder="F001" autocomplete="off" class="uppercase" />
        <x-input name="numero" label="Número" maxlength="20" placeholder="00012345" autocomplete="off" />

        <x-select name="almacen_id" label="Almacén que recibe">
            @forelse ($almLista as $a)
                <option value="{{ data_get($a, 'id') }}" @selected($almSel === (string) data_get($a, 'id'))>{{ data_get($a, 'nombre') }}</option>
            @empty
                <option value="">Sin almacenes</option>
            @endforelse
        </x-select>

        <x-select name="moneda" label="Moneda">
            @foreach ($monedas as $valor => $etiqueta)
                <option value="{{ $valor }}" @selected(old('moneda', 'PEN') === $valor)>{{ $etiqueta }}</option>
            @endforeach
        </x-select>

        <div class="sm:col-span-2">
            <x-input name="observacion" label="Observaciones (opcional)" maxlength="1000" placeholder="Ej.: Guía GR-001 recibida conforme." />
        </div>
    </div>
</x-card>
