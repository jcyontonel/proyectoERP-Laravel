{{-- Orden de compra imprimible (A4). Variables: $orden (proveedor, almacen, detalles.producto, usuario), $empresa --}}
@php $o = $orden; @endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Orden de compra {{ $o->numero_completo }}</title>
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
        .obs { margin-top: 20px; white-space: pre-line; border-top: 1px solid #e2e8f0; padding-top: 10px; }
        .firma { margin-top: 60px; width: 240px; border-top: 1px solid #1b2b3d; text-align: center; padding-top: 4px; }
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
        </div>
        <div class="doc">
            <span class="muted">RUC {{ $empresa->ruc }}</span>
            <strong>ORDEN DE COMPRA</strong>
            <span>{{ $o->numero_completo }}</span>
        </div>
    </div>

    <div class="bloque">
        <div><span class="muted">Proveedor:</span> {{ $o->proveedor?->nombre }}</div>
        <div><span class="muted">Emisión:</span> {{ $o->fecha_emision->format('d/m/Y') }}</div>
        <div><span class="muted">{{ $o->proveedor?->tipo_doc_etiqueta ?: 'Documento' }}:</span> {{ $o->proveedor?->numero_documento }}</div>
        <div><span class="muted">Entrega esperada:</span> {{ $o->fecha_entrega?->format('d/m/Y') ?? 'A coordinar' }}</div>
        <div><span class="muted">Entregar en:</span> {{ $o->almacen?->nombre }}{{ $o->almacen?->direccion ? ' — ' . $o->almacen->direccion : '' }}</div>
        <div><span class="muted">Pago:</span> {{ $o->condicion_pago === 'credito' ? 'Crédito' : 'Contado' }} · Soles (PEN)</div>
    </div>

    <table>
        <thead><tr><th>#</th><th>Descripción</th><th>Unidad</th><th class="r">Cant.</th><th class="r">Costo unit.</th><th class="r">Importe</th></tr></thead>
        <tbody>
            @foreach ($o->detalles as $d)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $d->descripcion }}</td>
                    <td>{{ $d->producto?->tipoUnidad?->descripcion }}</td>
                    <td class="r">{{ rtrim(rtrim(number_format($d->cantidad, 4, '.', ''), '0'), '.') }}</td>
                    <td class="r">{{ number_format($d->costo_unitario, 2) }}</td>
                    <td class="r">{{ number_format($d->subtotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totales">
        <div><span>Subtotal</span><span>S/ {{ number_format($o->subtotal, 2) }}</span></div>
        <div><span>IGV</span><span>S/ {{ number_format($o->total_impuestos, 2) }}</span></div>
        <div class="total"><span>Total</span><span>S/ {{ number_format($o->total, 2) }}</span></div>
    </div>

    @if ($o->observacion)
        <div class="obs"><strong>Observaciones</strong><br>{{ $o->observacion }}</div>
    @endif
    <div class="firma">{{ $o->usuario?->name ?? 'Autorizado por' }}</div>
</body>
</html>
