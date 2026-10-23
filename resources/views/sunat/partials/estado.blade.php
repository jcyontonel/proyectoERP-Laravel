{{-- Estado en SUNAT de una venta o nota. Variables: $sunat (ComprobanteSunat|null), $aplica (bool: es factura/boleta/nota) --}}
@if ($aplica ?? true)
    <x-card title="SUNAT" icon="upload-cloud">
        @if (! $sunat)
            <p class="text-sm text-slate-500">
                @if (app(\App\Services\Facturacion\FacturacionElectronicaService::class)->activo())
                    Aún no se envió al facturador electrónico.
                @else
                    Facturación electrónica apagada: configura <code>FACTURADOR_URL</code> y <code>FACTURADOR_TOKEN</code>.
                @endif
            </p>
        @else
            <div class="flex flex-wrap items-center gap-2">
                <x-badge :tone="$sunat->tono()" dot>{{ $sunat->etiqueta() }}</x-badge>
                @if ($sunat->codigo_respuesta !== null)<span class="text-xs text-slate-500">código {{ $sunat->codigo_respuesta }}</span>@endif
            </div>
            @if ($sunat->mensaje_respuesta)
                <p class="mt-2 text-sm text-slate-600">{{ $sunat->mensaje_respuesta }}</p>
            @endif
            @if ($sunat->observaciones)
                <ul class="mt-2 list-disc pl-5 text-xs text-amber-700">
                    @foreach ($sunat->observaciones as $obs)<li>{{ $obs }}</li>@endforeach
                </ul>
            @endif
            @if ($sunat->baja_estado)
                <p class="mt-2 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
                    Baja en SUNAT: <strong>{{ ['por_pedir' => 'por pedir (sin conexión)', 'pendiente' => 'pendiente', 'enviado' => 'en proceso', 'aceptado' => 'aceptada', 'rechazado' => 'rechazada', 'error' => 'con error'][$sunat->baja_estado] ?? $sunat->baja_estado }}</strong>
                    @if ($sunat->ticket) · ticket {{ $sunat->ticket }}@endif
                </p>
            @endif
            @if ($sunat->hash)
                <p class="mt-2 break-all text-[11px] text-slate-400">Hash: {{ $sunat->hash }}</p>
            @endif
            <p class="mt-1 text-[11px] text-slate-400">{{ $sunat->intentos }} {{ str('envío')->plural($sunat->intentos) }}@if ($sunat->respondido_at) · última respuesta {{ $sunat->respondido_at->diffForHumans() }}@endif</p>

            <div class="mt-3 flex flex-wrap gap-2">
                @if ($sunat->esReenviable() || $sunat->baja_estado === 'por_pedir')
                    <form method="POST" action="{{ route('sunat.reenviar', $sunat) }}">
                        @csrf
                        <button type="submit" class="btn btn-secondary"><x-icon name="refresh" class="text-brand-600" /> Reenviar</button>
                    </form>
                @endif
                @if ($sunat->facturador_id)
                    <a href="{{ route('sunat.archivo', [$sunat, 'xml']) }}" class="btn btn-secondary"><x-icon name="download" class="text-brand-600" /> XML</a>
                    @if ($sunat->fueAceptado() || $sunat->estado === 'rechazado' || $sunat->estado === 'anulado')
                        <a href="{{ route('sunat.archivo', [$sunat, 'cdr']) }}" class="btn btn-secondary"><x-icon name="download" class="text-brand-600" /> CDR</a>
                    @endif
                @endif
            </div>
        @endif
    </x-card>
@endif
