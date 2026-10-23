{{-- Comprobante electrónico de un pago ($p, arreglo de la API del ERP: comprobante, nota_credito). Lo emite el ERP por el facturador. --}}
@php $c = $p['comprobante'] ?? null; $nc = $p['nota_credito'] ?? null; @endphp

@if ($c)
    <div class="space-y-1">
        <div class="flex flex-wrap items-center gap-1.5">
            <a href="{{ route('plataforma.pagos.comprobante.archivo', [$p['id'], 'impreso']) }}" target="_blank" rel="noopener" class="font-medium text-brand-600 hover:underline" title="{{ $c['tipo_nombre'] }} — representación impresa">{{ $c['numero'] }}</a>
            <x-plataforma::badge :tone="$c['tono']" shape="box">{{ $c['aceptado'] ? 'SUNAT ✓' : $c['estado_nombre'] }}</x-plataforma::badge>
        </div>
        @if (! $c['aceptado'] && $c['mensaje'])
            <p class="max-w-56 text-[11px] text-slate-500" title="{{ $c['mensaje'] }}">{{ \Illuminate\Support\Str::limit($c['mensaje'], 90) }}</p>
        @endif
        @if ($c['baja_estado'])
            <p class="text-[11px] text-slate-500">Baja: {{ ['por_pedir' => 'por pedir', 'enviado' => 'en proceso', 'aceptado' => 'aceptada', 'rechazado' => 'rechazada', 'error' => 'con error'][$c['baja_estado']] ?? $c['baja_estado'] }}</p>
        @endif
        @if ($nc)
            <p class="text-[11px] text-slate-500">NC <a href="{{ route('plataforma.pagos.comprobante.archivo', [$p['id'], 'nota-xml']) }}" class="hover:underline">{{ $nc['numero'] }}</a> · {{ $nc['estado_nombre'] }}</p>
        @endif
        <div class="flex flex-wrap gap-2 text-[11px]">
            @if ($c['tiene_archivos'])
                <a href="{{ route('plataforma.pagos.comprobante.archivo', [$p['id'], 'xml']) }}" class="text-slate-500 hover:underline">XML</a>
                @if ($c['aceptado'] || in_array($c['estado'], ['rechazado', 'anulado'], true))
                    <a href="{{ route('plataforma.pagos.comprobante.archivo', [$p['id'], 'cdr']) }}" class="text-slate-500 hover:underline">CDR</a>
                @endif
            @endif
            @if ($c['reenviable'] || ($nc['reenviable'] ?? false))
                <form method="POST" action="{{ route('plataforma.pagos.comprobante', $p['id']) }}" class="inline">
                    @csrf
                    <button type="submit" class="font-semibold text-brand-600 hover:underline">Reenviar</button>
                </form>
            @endif
        </div>
    </div>
@elseif ($p['estado'] === 'pagado' && $p['monto'] > 0)
    <form method="POST" action="{{ route('plataforma.pagos.comprobante', $p['id']) }}" class="flex items-center gap-1.5">
        @csrf
        <select name="tipo" class="rounded-md border border-slate-200 bg-white px-1.5 py-1 text-[11px]" aria-label="Tipo de comprobante">
            <option value="factura">Factura</option>
            <option value="boleta">Boleta</option>
        </select>
        <button type="submit" class="text-[11px] font-semibold text-brand-600 hover:underline">Emitir</button>
    </form>
@else
    <span class="text-slate-400">—</span>
@endif
