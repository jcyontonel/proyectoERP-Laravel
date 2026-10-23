<div class="grid gap-5 sm:grid-cols-2 {{ $class ?? '' }}">
    <x-card title="Proveedor" icon="building">
        @if ($compra->proveedor)
            @php $prov = $compra->proveedor; @endphp
            <dl class="divide-y divide-slate-100">
                <x-dato label="Razón social">{{ $prov->nombre }}</x-dato>
                <x-dato label="Documento">{{ $prov->documento_etiqueta }}</x-dato>
                @if ($prov->contacto_nombre)
                    <x-dato label="Contacto">{{ $prov->contacto_nombre }}@if ($prov->contacto_cargo) <span class="text-slate-500">· {{ $prov->contacto_cargo }}</span>@endif</x-dato>
                @endif
                @if ($prov->telefono || $prov->celular)
                    <x-dato label="Teléfono">{{ collect([$prov->telefono, $prov->celular])->filter()->implode(' / ') }}</x-dato>
                @endif
                @if ($prov->email)
                    <x-dato label="Correo"><span class="break-all">{{ $prov->email }}</span></x-dato>
                @endif
            </dl>
        @else
            <p class="text-sm text-slate-500">Proveedor no disponible.</p>
        @endif
    </x-card>

    <x-card title="Datos del comprobante" icon="file-text">
        <dl class="divide-y divide-slate-100">
            <x-dato label="Comprobante">{{ $compra->tipo_etiqueta }}</x-dato>
            <x-dato label="Serie y número">{{ ($compra->serie && $compra->numero) ? $compra->serie . '-' . $compra->numero : 'Sin número' }}</x-dato>
            <x-dato label="Emisión">{{ $compra->fecha_emision->translatedFormat('d M Y') }}</x-dato>
            <x-dato label="Almacén">{{ $compra->almacen?->nombre ?? '—' }}</x-dato>
            @foreach ($compra->ordenesCompra as $orden)
                <x-dato label="Orden de compra"><a href="{{ route('ordenes.show', $orden) }}" class="font-medium text-brand-600 hover:underline">{{ $orden->numero_completo }}</a></x-dato>
            @endforeach
            <x-dato label="Registrada">{{ $compra->created_at?->translatedFormat('d M Y · h:i A') }}</x-dato>
            <x-dato label="Registrada por">{{ $compra->usuario?->name ?? '—' }}</x-dato>
        </dl>

        @if ($compra->observacion)
            <p class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
                <strong class="text-ink">Observaciones:</strong> {{ $compra->observacion }}
            </p>
        @endif
    </x-card>
</div>
