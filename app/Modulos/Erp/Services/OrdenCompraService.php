<?php

namespace App\Modulos\Erp\Services;

use App\Modulos\Erp\Models\Compra;
use App\Modulos\Nucleo\Models\Empresa;
use App\Modulos\Erp\Models\OrdenCompra;
use App\Modulos\Catalogo\Models\Producto;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Órdenes de compra: el pedido al proveedor. No mueven stock.
 *  - Se reciben registrando compras desde la orden (formulario de compra ya llenado con lo pendiente).
 *  - Cada compra suma a cantidad_recibida; la orden pasa a parcial o recibida. Si la compra se anula, se revierte.
 *  - Costos sin IGV; con afecto_igv se agrega el IGV de la empresa.
 */
class OrdenCompraService
{
    public function __construct(private CorrelativoService $correlativos)
    {
    }

    /**
     * @param  array{proveedor_id: int, almacen_id: int, fecha_emision: string, fecha_entrega?: ?string, condicion_pago?: string, afecto_igv?: bool, productos: array<int, array{id: int, cantidad: float, costo: float}>, observacion?: ?string}  $d
     */
    public function guardar(array $d, int $userId, int $empresaId, ?OrdenCompra $existente = null): OrdenCompra
    {
        return DB::transaction(function () use ($d, $userId, $empresaId, $existente) {
            if ($existente && ! OrdenCompra::query()->lockForUpdate()->findOrFail($existente->id)->esEditable()) {
                throw ValidationException::withMessages(['orden' => 'Solo se editan órdenes que aún no reciben mercadería.']);
            }
            $empresa = Empresa::findOrFail($empresaId);
            $productos = Producto::query()->deEmpresa($empresaId)->whereIn('id', collect($d['productos'])->pluck('id'))->get()->keyBy('id');

            $afecto = (bool) ($d['afecto_igv'] ?? true);
            $igvPct = $afecto ? (float) $empresa->igv : 0.0;
            $lineas = [];
            foreach ($d['productos'] as $l) {
                $p = $productos[$l['id']] ?? throw ValidationException::withMessages(['productos' => 'Uno de los productos ya no está disponible.']);
                $cant = round((float) $l['cantidad'], 4);
                $costo = round((float) $l['costo'], 6);
                $base = round($cant * $costo, 2);
                $igv = round($base * $igvPct / 100, 2);
                $lineas[] = [
                    'producto_id' => $p->id, 'descripcion' => Str::limit($p->nombre, 255, ''), 'cantidad' => $cant,
                    'costo_unitario' => $costo, 'subtotal' => $base, 'total_impuestos' => $igv, 'total' => round($base + $igv, 2),
                ];
            }

            $cabecera = [
                'proveedor_id'    => $d['proveedor_id'],
                'almacen_id'      => $d['almacen_id'],
                'fecha_emision'   => Carbon::parse($d['fecha_emision'])->setTimeFrom(now()),
                'fecha_entrega'   => $d['fecha_entrega'] ?? null,
                'condicion_pago'  => $d['condicion_pago'] ?? 'contado',
                'afecto_igv'      => $afecto,
                'subtotal'        => round(array_sum(array_column($lineas, 'subtotal')), 2),
                'total_impuestos' => round(array_sum(array_column($lineas, 'total_impuestos')), 2),
                'total'           => round(array_sum(array_column($lineas, 'total')), 2),
                'observacion'     => $d['observacion'] ?? null,
            ];

            if ($existente) {
                $existente->update($cabecera);
                $existente->detalles()->delete();
                $orden = $existente;
            } else {
                [$serie, $numero] = $this->correlativos->emitir($empresaId, 'orden_compra');
                $orden = OrdenCompra::create($cabecera + [
                    'empresa_id' => $empresaId, 'user_id' => $userId, 'serie' => $serie, 'numero' => $numero,
                    'moneda' => 'PEN', 'estado' => OrdenCompra::EMITIDA,
                ]);
            }
            $orden->detalles()->createMany($lineas);

            return $orden->refresh();
        });
    }

    /** Datos para abrir el formulario de compra con lo que falta recibir (old input) */
    public function datosParaCompra(OrdenCompra $orden): array
    {
        if (! $orden->esRecibible()) {
            throw ValidationException::withMessages(['orden' => 'La orden ya fue recibida, cerrada o anulada.']);
        }
        $pendientes = $orden->detalles->filter(fn ($d) => $d->pendiente() > 0);

        return [
            'orden_compra_id' => $orden->id,
            'proveedor_id'    => $orden->proveedor_id,
            'almacen_id'      => $orden->almacen_id,
            'condicion_pago'  => $orden->condicion_pago,
            'afecto_igv'      => $orden->afecto_igv ? 1 : 0,
            'precios_incluyen_igv' => 0,   // los costos de la orden son sin IGV
            'productos'       => $pendientes->map(fn ($d) => ['id' => $d->producto_id, 'cantidad' => $d->pendiente(), 'costo' => $d->costo_unitario])->values()->all(),
        ];
    }

    /** Lo llama CompraService al registrar una compra que viene de la orden */
    public function registrarRecepcion(int $ordenId, Compra $compra): void
    {
        $orden = OrdenCompra::query()->deEmpresa($compra->empresa_id)->lockForUpdate()->with('detalles')->find($ordenId);
        if (! $orden || ! $orden->esRecibible()) {
            throw ValidationException::withMessages(['orden_compra_id' => 'La orden de compra ya no admite recepciones.']);
        }
        if ($orden->proveedor_id !== $compra->proveedor_id) {
            throw ValidationException::withMessages(['proveedor_id' => 'La compra debe ser del mismo proveedor que la orden.']);
        }

        // Lo recibido se reparte en las líneas pendientes del mismo producto (lo que exceda no cuenta)
        $tomado = [];
        foreach ($compra->detalles as $det) {
            $porRecibir = (float) $det->cantidad;
            foreach ($orden->detalles->where('producto_id', $det->producto_id) as $linea) {
                $toma = min($porRecibir, $linea->pendiente());
                if ($toma > 0) {
                    $linea->update(['cantidad_recibida' => round($linea->cantidad_recibida + $toma, 4)]);
                    $porRecibir = round($porRecibir - $toma, 4);
                    $tomado[$linea->id] = round(($tomado[$linea->id] ?? 0) + $toma, 4);
                }
            }
        }

        $orden->compras()->attach($compra->id, ['cantidades' => json_encode($tomado)]);
        $this->recalcularEstado($orden);
    }

    /** Lo llama CompraService al anular una compra: devuelve lo recibido a pendiente */
    public function revertirRecepcion(Compra $compra): void
    {
        $orden = $compra->ordenesCompra()->lockForUpdate()->with('detalles')->first();
        if (! $orden) {
            return;
        }

        // Se resta exactamente lo que esta compra sumó (el excedente no se había contado)
        $tomado = json_decode((string) $orden->pivot->cantidades, true) ?: [];
        foreach ($orden->detalles as $linea) {
            if ($cant = (float) ($tomado[$linea->id] ?? 0)) {
                $linea->update(['cantidad_recibida' => max(round($linea->cantidad_recibida - $cant, 4), 0)]);
            }
        }

        $orden->compras()->detach($compra->id);
        if ($orden->estado !== OrdenCompra::ANULADA) {
            $orden->update(['estado' => OrdenCompra::EMITIDA]);   // se recalcula abajo
            $this->recalcularEstado($orden->refresh());
        }
    }

    /** Cerrar: recibida en parte y no se esperará el resto */
    public function cerrar(OrdenCompra $orden): void
    {
        if ($orden->estado !== OrdenCompra::PARCIAL) {
            throw ValidationException::withMessages(['orden' => 'Solo se cierran órdenes recibidas en parte.']);
        }
        $orden->update(['estado' => OrdenCompra::CERRADA]);
    }

    /** Anular: solo si aún no recibió nada */
    public function anular(OrdenCompra $orden): void
    {
        if ($orden->estado !== OrdenCompra::EMITIDA || $orden->compras()->exists()) {
            throw ValidationException::withMessages(['orden' => 'Solo se anulan órdenes que aún no reciben mercadería; si ya recibió una parte, ciérrala.']);
        }
        $orden->update(['estado' => OrdenCompra::ANULADA]);
    }

    private function recalcularEstado(OrdenCompra $orden): void
    {
        $orden->load('detalles');
        $recibido = $orden->detalles->sum('cantidad_recibida');
        $pendiente = $orden->detalles->sum(fn ($d) => $d->pendiente());

        $orden->update(['estado' => match (true) {
            $pendiente <= 0  => OrdenCompra::RECIBIDA,
            $recibido > 0    => OrdenCompra::PARCIAL,
            default          => OrdenCompra::EMITIDA,
        }]);
    }
}
