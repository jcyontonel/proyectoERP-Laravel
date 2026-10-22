{{-- Reporte imprimible. Variables: $ventas, $empresa, $filtros --}}
@php
    $metodos    = config('ventas.metodos_pago');
    $completas  = $ventas->where('estado', \App\Models\Venta::COMPLETADA);
    $totalVendido = $completas->sum('total');
    $etiquetas  = collect([
        'q'      => 'Búsqueda',
        'tipo'   => 'Tipo',
        'estado' => 'Estado',
        'desde'  => 'Desde',
        'hasta'  => 'Hasta',
    ])->map(fn ($nombre, $k) => filled($filtros[$k] ?? null) ? "{$nombre}: {$filtros[$k]}" : null)->filter()->implode(' · ');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reporte de ventas</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 24px; font: 12px/1.4 system-ui, -apple-system, "Segoe UI", sans-serif; color: #1b2b3d; }
        h1 { margin: 0 0 4px; font-size: 20px; }
        .muted { color: #64748b; }
        table { width: 100%; margin-top: 16px; border-collapse: collapse; }
        th, td { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: top; }
        th { background: #f1f5f9; font-size: 11px; text-transform: uppercase; letter-spacing: .03em; }
        .r { text-align: right; } .anulada { color: #94a3b8; text-decoration: line-through; }
        tfoot td { font-weight: 700; border-top: 2px solid #1b2b3d; border-bottom: 0; }
        .acciones { margin-bottom: 16px; }
        .acciones button { padding: 8px 16px; border: 0; border-radius: 8px; background: #1773ab; color: #fff; font-size: 14px; cursor: pointer; }
        @@page { size: A4 landscape; margin: 12mm; }
        @@media print { .no-print { display: none !important; } body { padding: 0; } }
    </style>
</head>
<body>
    <div class="acciones no-print">
        <button type="button" onclick="window.print()">Imprimir / Guardar como PDF</button>
    </div>

    <h1>Reporte de ventas</h1>
    <p class="muted">
        {{ $empresa?->razon_social }} @if ($empresa?->ruc) · RUC {{ $empresa->ruc }} @endif<br>
        Generado el {{ now()->translatedFormat('d M Y H:i') }}
        @if ($etiquetas) · {{ $etiquetas }} @endif
        · {{ $ventas->count() }} comprobantes
    </p>

    <table>
        <thead>
            <tr>
                <th>N° Comprobante</th><th>Fecha</th><th>Cliente</th><th>Tipo</th><th>Pago</th>
                <th class="r">Base</th><th class="r">IGV</th><th class="r">Total</th><th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($ventas as $v)
                <tr @class(['anulada' => $v->esAnulada()])>
                    <td>{{ $v->numero_completo }}</td>
                    <td>{{ $v->fecha_emision->format('d/m/Y H:i') }}</td>
                    <td>{{ $v->cliente?->nombre_completo ?: 'Público general' }}<br><span class="muted">{{ $v->cliente?->documento_etiqueta }}</span></td>
                    <td>{{ $v->tipo_etiqueta }}</td>
                    <td>{{ $metodos[$v->metodo_pago] ?? $v->metodo_pago }}</td>
                    <td class="r">{{ number_format($v->subtotal, 2) }}</td>
                    <td class="r">{{ number_format($v->total_impuestos, 2) }}</td>
                    <td class="r">{{ number_format($v->total, 2) }}</td>
                    <td>{{ ucfirst($v->estado) }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="muted" style="text-align:center;padding:24px">No hay ventas con estos filtros.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="7">Total vendido (solo completadas: {{ $completas->count() }})</td>
                <td class="r">S/ {{ number_format($totalVendido, 2) }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
