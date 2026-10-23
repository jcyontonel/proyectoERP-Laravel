{{-- Arqueo imprimible (ticket 80 mm). $sesion, $resumen, $movimientos --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Arqueo de caja · {{ $sesion->caja?->nombre }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0 auto; max-width: 80mm; padding: 4mm; font: 12px/1.35 ui-monospace, Menlo, Consolas, monospace; color: #000; }
        h1 { margin: 0; font-size: 14px; text-align: center; }
        p { margin: 0; }
        .c { text-align: center; }
        .linea { border-top: 1px dashed #000; margin: 6px 0; }
        .fila { display: flex; justify-content: space-between; gap: 8px; }
        .b { font-weight: 700; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body onload="window.print()">
    <h1>{{ $sesion->empresa?->nombre_comercial ?? $sesion->empresa?->razon_social }}</h1>
    <p class="c">RUC {{ $sesion->empresa?->ruc }}</p>
    <p class="c b" style="margin-top:4px">ARQUEO DE CAJA</p>
    <div class="linea"></div>
    <div class="fila"><span>Caja</span><span>{{ $sesion->caja?->nombre }}</span></div>
    <div class="fila"><span>Cajero</span><span>{{ $sesion->usuario?->nombre_completo }}</span></div>
    <div class="fila"><span>Apertura</span><span>{{ $sesion->fecha_apertura->format('d/m/Y H:i') }}</span></div>
    <div class="fila"><span>Cierre</span><span>{{ $sesion->fecha_cierre?->format('d/m/Y H:i') ?? 'Abierta' }}</span></div>
    <div class="linea"></div>
    <div class="fila"><span>Monto inicial</span><span>{{ number_format($resumen['apertura'], 2) }}</span></div>
    <div class="fila"><span>Ventas ({{ $resumen['ventas'] }})</span><span>{{ number_format($resumen['ventas_total'], 2) }}</span></div>
    @foreach ($resumen['cobros'] as $c)
        <div class="fila"><span>&nbsp; {{ $c['label'] }}</span><span>{{ number_format($c['monto'], 2) }}</span></div>
    @endforeach
    <div class="fila"><span>Otros ingresos</span><span>{{ number_format($resumen['ingresos'], 2) }}</span></div>
    <div class="fila"><span>Egresos</span><span>-{{ number_format($resumen['egresos'], 2) }}</span></div>
    @if ($resumen['total_pagos'] > 0)
        <div class="fila"><span>Pagos a proveedores</span><span>-{{ number_format($resumen['total_pagos'], 2) }}</span></div>
    @endif
    <div class="linea"></div>
    <div class="fila b"><span>EFECTIVO ESPERADO</span><span>{{ number_format($resumen['efectivo'], 2) }}</span></div>
    @unless ($sesion->estaAbierta())
        <div class="fila b"><span>EFECTIVO CONTADO</span><span>{{ number_format($sesion->monto_contado, 2) }}</span></div>
        <div class="fila b"><span>DIFERENCIA</span><span>{{ number_format($sesion->diferencia, 2) }}</span></div>
    @endunless
    @if ($sesion->arqueo)
        <div class="linea"></div>
        @foreach (collect($sesion->arqueo)->sortKeysDesc() as $valor => $cantidad)
            <div class="fila"><span>S/ {{ $valor }} x {{ $cantidad }}</span><span>{{ number_format($valor * $cantidad, 2) }}</span></div>
        @endforeach
    @endif
    @if ($movimientos->where('estado', 'registrado')->isNotEmpty())
        <div class="linea"></div>
        <p class="b">Movimientos manuales</p>
        @foreach ($movimientos->where('estado', 'registrado') as $m)
            <div class="fila"><span>{{ $m->esIngreso() ? '+' : '-' }} {{ \Illuminate\Support\Str::limit($m->concepto, 22) }}</span><span>{{ number_format($m->monto, 2) }}</span></div>
        @endforeach
    @endif
    <div class="linea"></div>
    <p class="c" style="margin-top:18px">________________________</p>
    <p class="c">Firma del cajero</p>
    <p class="c no-print" style="margin-top:10px"><button onclick="window.print()">Imprimir</button></p>
</body>
</html>
