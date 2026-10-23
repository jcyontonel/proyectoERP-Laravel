<div class="grid gap-5 sm:grid-cols-2 {{ $class ?? '' }}">
    <x-card title="Cliente" icon="user">
        @if ($venta->cliente)
            <dl class="divide-y divide-slate-100">
                <x-dato label="Nombre">{{ $venta->cliente->nombre_completo }}</x-dato>
                <x-dato label="Documento">{{ $venta->cliente->documento_etiqueta }}</x-dato>
                @if ($venta->cliente->correo)
                    <x-dato label="Correo"><span class="break-all">{{ $venta->cliente->correo }}</span></x-dato>
                @endif
                @if ($venta->cliente->telefono)
                    <x-dato label="Teléfono">{{ $venta->cliente->telefono }}</x-dato>
                @endif
                @if ($venta->cliente->direccion)
                    <x-dato label="Dirección">{{ $venta->cliente->direccion }}</x-dato>
                @endif
            </dl>
        @else
            <p class="rounded-lg border border-dashed border-slate-300 p-3.5 text-center text-xs text-slate-500">
                Venta al público general (sin cliente registrado).
            </p>
        @endif
    </x-card>

    <x-card title="Datos del comprobante" icon="file-text">
        <dl class="divide-y divide-slate-100">
            <x-dato label="Comprobante">{{ $venta->tipo_etiqueta }}</x-dato>
            <x-dato label="Serie y número">{{ $venta->numero_completo }}</x-dato>
            <x-dato label="Emisión">{{ $venta->fecha_emision->translatedFormat('d M Y') }} · {{ $venta->fecha_emision->format('h:i A') }}</x-dato>
            <x-dato label="Método de pago">{{ $venta->metodo_label }}</x-dato>
            <x-dato label="Almacén">{{ $venta->almacen?->nombre ?? '—' }}</x-dato>
            <x-dato label="Vendedor">{{ $venta->usuario?->name ?? '—' }}</x-dato>
        </dl>

        @if ($venta->observaciones)
            <p class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
                <strong class="text-ink">Observaciones:</strong> {{ $venta->observaciones }}
            </p>
        @endif
    </x-card>
</div>
