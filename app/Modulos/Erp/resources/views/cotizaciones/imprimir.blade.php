{{-- Cotización imprimible (A4). Variables: $cotizacion (cliente, detalles, usuario), $empresa --}}
@php $c = $cotizacion; @endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cotización {{ $c->numero_completo }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 24px; font: 12px/1.45 system-ui, -apple-system, "Segoe UI", sans-serif; color: #1b2b3d; }
        .cab { display: flex; justify-content: space-between; gap: 24px; align-items: flex-start; }
        .cab img { max-height: 70px; max-width: 180px; display: block; margin-bottom: 6px; }
        .doc { border: 2px solid #1773ab; border-radius: 8px; padding: 10px 18px; text-align: center; min-width: 210px; }
        .doc strong { display: block; font-size: 16px; }
        h1 { margin: 0; font-size: 16px; }
        .muted { color: #64748b; }
        .bloque { margin-top: 18px; display: grid; grid-template-columns: 1fr 1fr; gap: 6px 24px; }
        table { width: 100%; margin-top: 18px; border-collapse: collapse; }
        th, td { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: top; }
        th { background: #f1f5f9; font-size: 11px; text-transform: uppercase; letter-spacing: .03em; }
        .r { text-align: right; }
        .totales { margin-left: auto; margin-top: 12px; width: 280px; }
        .totales div { display: flex; justify-content: space-between; padding: 3px 0; }
        .totales .total { border-top: 2px solid #1b2b3d; font-weight: 700; font-size: 14px; margin-top: 4px; padding-top: 6px; }
        .condiciones { margin-top: 20px; white-space: pre-line; border-top: 1px solid #e2e8f0; padding-top: 10px; }
        .acciones { margin-bottom: 16px; }
        .acciones button { padding: 8px 16px; border: 0; border-radius: 8px; background: #1773ab; color: #fff; font-size: 14px; cursor: pointer; }
        @@page { size: A4; margin: 14mm; }
        @@media print { .no-print { display: none !important; } body { padding: 0; } }
    </style>
</head>
<body>
    <div class="acciones no-print"><button type="button" onclick="window.print()">Imprimir / Guardar como PDF</button></div>

    <div class="cab">
        <div>
            @if ($empresa->logo_path)<img src="{{ route('config.empresa.logo') }}" alt="">@endif
            <h1>{{ $empresa->nombre_comercial ?: $empresa->razon_social }}</h1>
            <div class="muted">{{ $empresa->razon_social }} · RUC {{ $empresa->ruc }}</div>
            <div class="muted">{{ $empresa->direccion }}</div>
            @if ($empresa->telefono || $empresa->email)<div class="muted">{{ collect([$empresa->telefono, $empresa->email])->filter()->implode(' · ') }}</div>@endif
        </div>
        <div class="doc">
            <span class="muted">RUC {{ $empresa->ruc }}</span>
            <strong>COTIZACIÓN</strong>
            <span>{{ $c->numero_completo }}</span>
        </div>
    </div>

    <div class="bloque">
        <div><span class="muted">Cliente:</span> {{ $c->cliente?->nombre_completo ?? 'A quien corresponda' }}</div>
        <div><span class="muted">Emisión:</span> {{ $c->fecha_emision->format('d/m/Y') }}</div>
        <div><span class="muted">Documento:</span> {{ $c->cliente?->documento_etiqueta ?? '—' }}</div>
        <div><span class="muted">Válida hasta:</span> {{ $c->vence_el->format('d/m/Y') }}</div>
        @if ($c->cliente?->direccion)<div><span class="muted">Dirección:</span> {{ $c->cliente->direccion }}</div>@endif
        <div><span class="muted">Moneda:</span> Soles (PEN)</div>
    </div>

    <table>
        <thead><tr><th>#</th><th>Descripción</th><th>Unidad</th><th class="r">Cant.</th><th class="r">P. unit.</th><th class="r">Desc.</th><th class="r">Importe</th></tr></thead>
        <tbody>
            @foreach ($c->detalles as $d)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $d->descripcion }}</td>
                    <td>{{ $d->tipoUnidad?->descripcion }}</td>
                    <td class="r">{{ rtrim(rtrim(number_format($d->cantidad, 4, '.', ''), '0'), '.') }}</td>
                    <td class="r">{{ number_format($d->precio_unitario, 2) }}</td>
                    <td class="r">{{ $d->descuento > 0 ? number_format($d->descuento, 2) : '' }}</td>
                    <td class="r">{{ number_format($d->total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totales">
        @if ($c->descuento > 0)<div><span>Descuento</span><span>− S/ {{ number_format($c->descuento, 2) }}</span></div>@endif
        <div><span>Op. gravada</span><span>S/ {{ number_format($c->subtotal, 2) }}</span></div>
        <div><span>IGV</span><span>S/ {{ number_format($c->total_impuestos, 2) }}</span></div>
        <div class="total"><span>Total</span><span>S/ {{ number_format($c->total, 2) }}</span></div>
    </div>

    @if ($c->condiciones)
        <div class="condiciones"><strong>Condiciones</strong><br>{{ $c->condiciones }}</div>
    @endif
    <p class="muted" style="margin-top:20px">Documento no válido como comprobante de pago.{{ $c->usuario ? ' Atendido por ' . $c->usuario->name . '.' : '' }}</p>
</body>
</html>
