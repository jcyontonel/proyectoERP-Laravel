{{-- Nota de crédito/débito imprimible (formato ticket). $nota con venta.empresa, venta.cliente, detalles, usuario --}}
@php
    $v = $nota->venta;
    $empresa = $v->empresa;
    $ancho = auth()->user()?->preferencias()->ticket_ancho === '58' ? 58 : 80;
    $dinero = fn ($n) => number_format($n, 2);
    $cant = fn ($x) => rtrim(rtrim(number_format($x, 2, '.', ''), '0'), '.');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $nota->numero_completo }} · {{ $nota->tipo_etiqueta }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font: 12px/1.35 "Courier New", ui-monospace, monospace; color: #000; background: #e5e7eb; }
        .ticket { width: {{ $ancho - 4 }}mm; margin: 12px auto; padding: 4mm; background: #fff; }
        .c { text-align: center; } .r { text-align: right; } .b { font-weight: 700; }
        .grande { font-size: 15px; } .chico { font-size: 11px; }
        hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; padding: 1px 0; }
        .fila { display: flex; justify-content: space-between; gap: 8px; }
        .sello { margin: 6px 0; padding: 3px; border: 2px solid #000; text-align: center; font-weight: 700; letter-spacing: 1px; }
        .acciones { display: flex; justify-content: center; gap: 8px; margin: 12px; font-family: system-ui, sans-serif; }
        .acciones button { padding: 8px 16px; border: 0; border-radius: 8px; font-size: 14px; cursor: pointer; background: #1773ab; color: #fff; }
        .acciones button.sec { background: #fff; color: #1b2b3d; border: 1px solid #cbd5e1; }
        @@page { size: {{ $ancho }}mm auto; margin: 0; }
        @@media print { body { background: #fff; } .ticket { width: 100%; margin: 0; padding: 3mm; } .no-print { display: none !important; } }
    </style>
</head>
<body>
    <div class="acciones no-print">
        <button type="button" onclick="window.print()">Imprimir</button>
        <button type="button" class="sec" onclick="window.close()">Cerrar</button>
    </div>

    <div class="ticket">
        <div class="c">
            <p class="b grande">{{ $empresa->nombre_comercial ?: $empresa->razon_social }}</p>
            @if ($empresa->nombre_comercial)<p class="chico">{{ $empresa->razon_social }}</p>@endif
            <p>RUC: {{ $empresa->ruc }}</p>
            @if ($empresa->direccion)<p class="chico">{{ $empresa->direccion }}</p>@endif
        </div>

        <hr>
        <p class="c b">{{ mb_strtoupper($nota->tipo_etiqueta) }} ELECTRÓNICA</p>
        <p class="c b grande">{{ $nota->numero_completo }}</p>
        @if ($nota->esAnulada())<p class="sello">*** ANULADA ***</p>@endif

        <hr>
        <p>Fecha: {{ $nota->fecha_emision->format('d/m/Y H:i') }}</p>
        <p>Documento que modifica: {{ $v->tipo_etiqueta }} {{ $v->numero_completo }}</p>
        <p>Motivo: {{ $nota->motivo_codigo }} {{ $nota->motivo_descripcion }}</p>
        <p>Cliente: {{ $v->cliente?->nombre_completo ?: 'PÚBLICO GENERAL' }}</p>
        @if ($v->cliente)<p>{{ $v->cliente->documento_etiqueta }}</p>@endif

        <hr>
        <table>
            <thead><tr class="b"><td>CANT. DESCRIPCIÓN</td><td class="r">IMPORTE</td></tr></thead>
            <tbody>
                @foreach ($nota->detalles as $d)
                    <tr><td>{{ $cant($d->cantidad) }} {{ $d->descripcion }}</td><td class="r">{{ $dinero($d->total) }}</td></tr>
                @endforeach
            </tbody>
        </table>

        <hr>
        <div class="fila"><span>OP. GRAVADA</span><span>S/ {{ $dinero($nota->subtotal) }}</span></div>
        <div class="fila"><span>IGV</span><span>S/ {{ $dinero($nota->total_impuestos) }}</span></div>
        <div class="fila b grande"><span>TOTAL</span><span>S/ {{ $dinero($nota->total) }}</span></div>
        <hr>
        <p class="chico">SON: {{ \App\Support\NumeroALetras::moneda($nota->total, config('ventas.moneda_nombre', 'SOLES')) }}</p>
        @if ($nota->usuario)<p class="chico">Emitió: {{ $nota->usuario->name }}</p>@endif
    </div>

    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
</body>
</html>
