{{-- Representación impresa del comprobante de un pago de suscripción. Variables: $pago (con comprobante, notaCredito, empresa, suscripcion.plan), $emisor --}}
@php
    $c = $pago->comprobante;
    $tipo = ['01' => 'FACTURA ELECTRÓNICA', '03' => 'BOLETA DE VENTA ELECTRÓNICA'][$c->tipo] ?? 'COMPROBANTE';
    $incluye = (bool) config('suscripciones.facturacion.precios_incluyen_igv');
    $igvPct = (float) ($emisor->igv ?: 18);
    [$base, $igv, $total] = \App\Modulos\Erp\Support\Montos::desglosar((float) $pago->monto, $igvPct, $incluye);
    $cli = $pago->empresa;
    $periodo = config('suscripciones.periodicidades.' . $pago->suscripcion?->periodicidad . '.0', '');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $c->numeroCompleto() }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 24px; font: 12px/1.45 system-ui, -apple-system, "Segoe UI", sans-serif; color: #1b2b3d; background: #fff; }
        .cab { display: flex; justify-content: space-between; gap: 24px; align-items: flex-start; }
        .doc { border: 2px solid #1773ab; border-radius: 8px; padding: 10px 18px; text-align: center; min-width: 230px; }
        .doc strong { display: block; font-size: 14px; margin: 2px 0; }
        h1 { margin: 0; font-size: 16px; } .muted { color: #64748b; }
        .bloque { margin-top: 18px; display: grid; grid-template-columns: 1fr 1fr; gap: 6px 24px; }
        table { width: 100%; margin-top: 18px; border-collapse: collapse; }
        th, td { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: top; }
        th { background: #f1f5f9; font-size: 11px; text-transform: uppercase; } .r { text-align: right; }
        .totales { margin-left: auto; margin-top: 12px; width: 280px; } .totales div { display: flex; justify-content: space-between; padding: 3px 0; }
        .totales .total { border-top: 2px solid #1b2b3d; font-weight: 700; font-size: 14px; margin-top: 4px; padding-top: 6px; }
        .pie { margin-top: 22px; border-top: 1px solid #e2e8f0; padding-top: 10px; font-size: 11px; }
        .estado { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; background: #f1f5f9; }
        .estado.ok { background: #ecfdf5; color: #047857; } .estado.mal { background: #fef2f2; color: #b91c1c; }
        .acciones { margin-bottom: 16px; } .acciones button { padding: 8px 16px; border: 0; border-radius: 8px; background: #1773ab; color: #fff; font-size: 14px; cursor: pointer; }
        @@page { size: A4; margin: 14mm; } @@media print { .no-print { display: none !important; } body { padding: 0; } }
    </style>
</head>
<body>
    <div class="acciones no-print"><button type="button" onclick="window.print()">Imprimir / Guardar como PDF</button></div>

    <div class="cab">
        <div>
            <h1>{{ $emisor->nombre_comercial ?: $emisor->razon_social }}</h1>
            <div class="muted">{{ $emisor->razon_social }}</div>
            <div class="muted">{{ $emisor->direccion }}</div>
            @if ($emisor->telefono || $emisor->email)<div class="muted">{{ collect([$emisor->telefono, $emisor->email])->filter()->implode(' · ') }}</div>@endif
        </div>
        <div class="doc">
            <span>RUC {{ $emisor->ruc }}</span>
            <strong>{{ $tipo }}</strong>
            <span>{{ $c->numeroCompleto() }}</span>
        </div>
    </div>

    <div class="bloque">
        <div><span class="muted">Cliente:</span> {{ $cli->razon_social }}</div>
        <div><span class="muted">Fecha de emisión:</span> {{ $c->created_at->format('d/m/Y') }}</div>
        <div><span class="muted">RUC:</span> {{ $cli->ruc }}</div>
        <div><span class="muted">Moneda:</span> Soles (PEN) · Contado</div>
        <div><span class="muted">Dirección:</span> {{ $cli->direccion ?: '—' }}</div>
        <div><span class="muted">Forma de pago:</span> {{ config('suscripciones.metodos_pago.' . $pago->metodo) ?? $pago->metodo }}{{ $pago->referencia ? ' · ' . $pago->referencia : '' }}</div>
    </div>

    <table>
        <thead><tr><th>Cant.</th><th>Unidad</th><th>Descripción</th><th class="r">P. unit.</th><th class="r">Importe</th></tr></thead>
        <tbody>
            <tr>
                <td>1</td><td>Servicio</td>
                <td>{{ config('suscripciones.facturacion.producto') }} — Plan {{ $pago->suscripcion?->plan?->nombre }}{{ $periodo ? " ({$periodo})" : '' }}
                    @if ($pago->periodo_desde)<br><span class="muted">Del {{ $pago->periodo_desde->format('d/m/Y') }} al {{ $pago->periodo_hasta->format('d/m/Y') }}</span>@endif
                    @if ($pago->oferta)<br><span class="muted">Oferta: {{ $pago->oferta->nombre }} (− S/ {{ number_format($pago->descuento, 2) }})</span>@endif
                </td>
                <td class="r">{{ number_format($total, 2) }}</td>
                <td class="r">{{ number_format($total, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="totales">
        <div><span>Op. gravada</span><span>S/ {{ number_format($base, 2) }}</span></div>
        <div><span>IGV ({{ rtrim(rtrim(number_format($igvPct, 2), '0'), '.') }} %)</span><span>S/ {{ number_format($igv, 2) }}</span></div>
        <div class="total"><span>Importe total</span><span>S/ {{ number_format($total, 2) }}</span></div>
    </div>

    <div class="pie">
        <p>Representación impresa de la {{ mb_strtolower($tipo) }}. Consulte su validez en <strong>www.sunat.gob.pe</strong>.</p>
        @if ($c->hash)<p class="muted">Hash: {{ $c->hash }}</p>@endif
        <p>Estado en SUNAT: <span @class(['estado', 'ok' => $c->fueAceptado(), 'mal' => in_array($c->estado, ['rechazado', 'error'], true)])>{{ $c->etiqueta() }}</span>
            @if ($c->baja_estado) · baja: {{ $c->baja_estado }}@endif
            @if ($pago->notaCredito) · anulado con la nota de crédito {{ $pago->notaCredito->numeroCompleto() }}@endif
        </p>
    </div>
</body>
</html>
