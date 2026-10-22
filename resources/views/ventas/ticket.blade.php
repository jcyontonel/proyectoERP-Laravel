{{-- Ticket de 80 mm. Variable: $venta con empresa, usuario, cliente.tipoDocumento, detalles.tipoUnidad --}}
@php
    $empresa = $venta->empresa;
    $ancho   = auth()->user()?->preferencias()->ticket_ancho === '58' ? 58 : 80;   // Preferencias del usuario
    $dinero  = fn ($n) => number_format($n, 2);
    $cant    = fn ($x) => rtrim(rtrim(number_format($x, 2, '.', ''), '0'), '.');
    $importe = fn ($d) => round($d->cantidad * $d->precio_unitario - $d->descuento, 2);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $venta->numero_completo }} · Ticket</title>
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
        @@media print {
            body { background: #fff; }
            .ticket { width: 100%; margin: 0; padding: 3mm; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="acciones no-print">
        <button type="button" onclick="window.print()">Imprimir</button>
        <button type="button" class="sec" onclick="window.close()">Cerrar</button>
    </div>

    <div class="ticket">
        <div class="c">
            @if ($empresa->logo_path)
                <img src="{{ route('config.empresa.logo') }}" alt="" style="display:block;max-width:60%;max-height:70px;margin:0 auto 6px">
            @endif
            <p class="b grande">{{ $empresa->nombre_comercial ?: $empresa->razon_social }}</p>
            @if ($empresa->nombre_comercial && $empresa->razon_social)
                <p class="chico">{{ $empresa->razon_social }}</p>
            @endif
            <p>RUC: {{ $empresa->ruc }}</p>
            @if ($empresa->direccion) <p class="chico">{{ $empresa->direccion }}</p> @endif
            @if ($empresa->telefono) <p class="chico">Tel.: {{ $empresa->telefono }}</p> @endif
        </div>

        <hr>
        <p class="c b">{{ strtoupper($venta->tipo_etiqueta) }}</p>
        <p class="c b grande">{{ $venta->numero_completo }}</p>

        @if ($venta->esAnulada())
            <p class="sello">*** ANULADO ***</p>
        @elseif ($venta->esBorrador())
            <p class="sello">BORRADOR · SIN VALOR</p>
        @endif

        <hr>
        <p>Fecha: {{ $venta->fecha_emision->format('d/m/Y H:i') }}</p>
        <p>Cliente: {{ $venta->cliente?->nombre_completo ?: 'PÚBLICO GENERAL' }}</p>
        @if ($venta->cliente) <p>{{ $venta->cliente->documento_etiqueta }}</p> @endif
        @if ($venta->usuario) <p>Atendió: {{ $venta->usuario->name }}</p> @endif

        <hr>
        <table>
            <thead>
                <tr class="b"><td>CANT. DESCRIPCIÓN</td><td class="r">IMPORTE</td></tr>
            </thead>
            <tbody>
                @foreach ($venta->detalles as $d)
                    <tr>
                        <td>{{ $cant($d->cantidad) }} {{ $d->descripcion }}</td>
                        <td class="r">{{ $dinero($importe($d)) }}</td>
                    </tr>
                    <tr class="chico">
                        <td colspan="2">&nbsp;&nbsp;{{ $cant($d->cantidad) }} x {{ $dinero($d->precio_unitario) }}@if ($d->descuento > 0) (desc. {{ $dinero($d->descuento) }})@endif</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <hr>
        @if ($venta->descuento > 0)
            <div class="fila"><span>DESCUENTO ({{ rtrim(rtrim(number_format($venta->descuento_porcentaje, 2, '.', ''), '0'), '.') }}%)</span><span>- {{ $dinero($venta->descuento) }}</span></div>
        @endif
        <div class="fila"><span>OP. GRAVADA</span><span>S/ {{ $dinero($venta->subtotal) }}</span></div>
        <div class="fila"><span>IGV ({{ (float) $empresa->igv }}%)</span><span>S/ {{ $dinero($venta->total_impuestos) }}</span></div>
        <div class="fila b grande"><span>TOTAL</span><span>S/ {{ $dinero($venta->total) }}</span></div>

        <hr>
        <p class="chico">SON: {{ \App\Support\NumeroALetras::moneda($venta->total, config('ventas.moneda_nombre', 'SOLES')) }}</p>
        <p class="chico">Pago: {{ $venta->metodo_label }}</p>
        @if ($venta->observaciones) <p class="chico">Obs.: {{ $venta->observaciones }}</p> @endif

        <hr>
        <p class="c">¡Gracias por su compra!</p>
    </div>

    <script>
        // Abre el diálogo de impresión al cargar (en borradores/anulados también, para revisarlo)
        window.addEventListener('load', () => setTimeout(() => window.print(), 300));
    </script>
    @if (request()->boolean('auto'))
        <script>window.addEventListener('load', () => window.print());</script>
    @endif
</body>
</html>
