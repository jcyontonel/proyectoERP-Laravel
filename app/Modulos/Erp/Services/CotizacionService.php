<?php

namespace App\Modulos\Erp\Services;

use App\Modulos\Erp\Models\Cotizacion;
use App\Modulos\Nucleo\Models\Empresa;
use App\Modulos\Catalogo\Models\Producto;
use App\Modulos\Erp\Models\Venta;
use App\Modulos\Erp\Support\Montos;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Cotizaciones: usan los precios del catálogo y el descuento global, con la misma fórmula que la venta.
 * No mueven stock ni consumen comprobantes. Al convertirse, el formulario de venta se abre con el cliente,
 * los productos y el descuento; al registrar la venta, la cotización queda "convertida".
 */
class CotizacionService
{
    public function __construct(private CorrelativoService $correlativos)
    {
    }

    /**
     * @param  array{cliente_id?: ?int, fecha_emision: string, vence_el?: ?string, descuento?: ?float, productos: array<int, array{id: int, cantidad: float}>, condiciones?: ?string, observacion?: ?string}  $d
     */
    public function guardar(array $d, int $userId, int $empresaId, ?Cotizacion $existente = null): Cotizacion
    {
        return DB::transaction(function () use ($d, $userId, $empresaId, $existente) {
            if ($existente && ! Cotizacion::query()->lockForUpdate()->findOrFail($existente->id)->esEditable()) {
                throw ValidationException::withMessages(['cotizacion' => 'Solo se editan cotizaciones emitidas (no aceptadas, convertidas, rechazadas ni anuladas).']);
            }
            $empresa = Empresa::findOrFail($empresaId);

            // Productos reales de la BD (nunca se confía en los precios del navegador); se suman líneas repetidas
            $pedido = collect($d['productos'])->groupBy('id')->map(fn ($g) => round($g->sum(fn ($l) => (float) $l['cantidad']), 4));
            $productos = Producto::query()->deEmpresa($empresaId)->whereIn('id', $pedido->keys())->get()->keyBy('id');
            if ($productos->count() !== $pedido->count()) {
                throw ValidationException::withMessages(['productos' => 'Uno de los productos ya no está disponible.']);
            }

            $lineas = $pedido->map(fn ($cant, $id) => [
                'p' => $productos[$id], 'cant' => $cant, 'precio' => (float) $productos[$id]->precio_unitario,
                'gross' => round($productos[$id]->precio_unitario * $cant, 2),
            ])->values()->all();
            $c = Montos::calcular($lineas, (float) ($d['descuento'] ?? 0), (float) $empresa->igv, (bool) $empresa->precios_incluyen_igv);

            $fecha = Carbon::parse($d['fecha_emision'])->setTimeFrom(now());
            $cabecera = [
                'cliente_id'           => $d['cliente_id'] ?? null,
                'fecha_emision'        => $fecha,
                'vence_el'             => $d['vence_el'] ?? $fecha->copy()->addDays((int) config('ventas.cotizacion_dias_validez', 15))->toDateString(),
                'descuento_porcentaje' => $c['pct'],
                'descuento'            => $c['descuento'],
                'subtotal'             => $c['base'],
                'total_impuestos'      => $c['igv'],
                'total'                => $c['total'],
                'condiciones'          => $d['condiciones'] ?? null,
                'observacion'          => $d['observacion'] ?? null,
            ];

            if ($existente) {
                $existente->update($cabecera);
                $existente->detalles()->delete();
                $cot = $existente;
            } else {
                [$serie, $numero] = $this->correlativos->emitir($empresaId, 'cotizacion');
                $cot = Cotizacion::create($cabecera + [
                    'empresa_id' => $empresaId, 'user_id' => $userId, 'serie' => $serie, 'numero' => $numero,
                    'moneda' => 'PEN', 'estado' => Cotizacion::EMITIDA,
                ]);
            }

            $cot->detalles()->createMany(array_map(fn ($l) => [
                'producto_id' => $l['p']->id, 'tipo_unidad_id' => $l['p']->tipo_unidad_id, 'descripcion' => Str::limit($l['p']->nombre, 255, ''),
                'cantidad' => $l['cant'], 'precio_unitario' => $l['precio'], 'descuento' => $l['desc'],
                'subtotal' => $l['base'], 'total_impuestos' => $l['igv'], 'total' => $l['total'],
            ], $c['lineas']));

            return $cot->refresh();
        });
    }

    /** aceptada | rechazada | anulada (desde emitida o aceptada) */
    public function cambiarEstado(Cotizacion $cot, string $estado): void
    {
        $permitidos = [
            Cotizacion::ACEPTADA  => [Cotizacion::EMITIDA],
            Cotizacion::RECHAZADA => [Cotizacion::EMITIDA, Cotizacion::ACEPTADA],
            Cotizacion::ANULADA   => [Cotizacion::EMITIDA, Cotizacion::ACEPTADA, Cotizacion::RECHAZADA],
            Cotizacion::EMITIDA   => [Cotizacion::RECHAZADA],   // reabrir una rechazada
        ];
        if (! in_array($cot->estado, $permitidos[$estado] ?? [], true)) {
            throw ValidationException::withMessages(['estado' => 'La cotización no puede pasar de «' . $cot->estado . '» a «' . $estado . '».']);
        }
        $cot->update(['estado' => $estado]);
    }

    /** Datos para abrir el formulario de venta ya llenado (old input) */
    public function datosParaVenta(Cotizacion $cot): array
    {
        if (! $cot->esConvertible()) {
            throw ValidationException::withMessages(['cotizacion' => 'Solo se convierten cotizaciones emitidas o aceptadas.']);
        }

        return [
            'cotizacion_id' => $cot->id,
            'cliente_id'    => $cot->cliente_id,
            'descuento'     => $cot->descuento_porcentaje,
            'productos'     => $cot->detalles->map(fn ($d) => ['id' => $d->producto_id, 'cantidad' => (float) $d->cantidad])->all(),
        ];
    }

    /** Lo llama VentaService al guardar una venta que vino de una cotización */
    public function marcarConvertida(int $cotizacionId, Venta $venta): void
    {
        $cot = Cotizacion::query()->deEmpresa($venta->empresa_id)->lockForUpdate()->find($cotizacionId);
        if ($cot && $cot->esConvertible()) {
            $cot->update(['estado' => Cotizacion::CONVERTIDA, 'venta_id' => $venta->id]);
        }
    }

    /** Si la venta se anula, la cotización vuelve a quedar aceptada (puede convertirse de nuevo) */
    public function liberar(Venta $venta): void
    {
        Cotizacion::query()->where('venta_id', $venta->id)->update(['estado' => Cotizacion::ACEPTADA, 'venta_id' => null]);
    }
}
