<?php

namespace App\Modulos\Erp\Services;

use App\Modulos\Erp\Models\AlmacenProducto;
use App\Modulos\Erp\Models\ExistenciaLote;
use App\Modulos\Erp\Models\Kardex;
use App\Modulos\Erp\Models\Lote;
use App\Modulos\Catalogo\Models\Producto;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Único punto que modifica el stock. Cada movimiento:
 *  1) bloquea y actualiza almacen_producto (y existencias_lote si hay lote),
 *  2) deja la huella en kardex (stock anterior → nuevo).
 * Llamar SIEMPRE dentro de una transacción. Los servicios (es_servicio) no deben llegar aquí.
 *
 * $ctx: empresa_id, user_id, ref ['tipo','id','serie','numero'], costo (unitario, sin IGV),
 *       lote (?Lote), fecha (Carbon), obs (string)
 */
class InventarioService
{
    public const INGRESO = 'ingreso';
    public const EGRESO  = 'egreso';

    public function ingreso(Producto $producto, int $almacenId, float $cantidad, array $ctx): Kardex
    {
        return $this->mover(self::INGRESO, $producto, $almacenId, $cantidad, $ctx);
    }

    public function egreso(Producto $producto, int $almacenId, float $cantidad, array $ctx): Kardex
    {
        return $this->mover(self::EGRESO, $producto, $almacenId, $cantidad, $ctx);
    }

    /** Stock actual del producto en un almacén (sin bloquear) */
    public function stock(int $productoId, int $almacenId): float
    {
        return (float) (AlmacenProducto::query()
            ->where('almacen_id', $almacenId)->where('producto_id', $productoId)->value('stock') ?? 0);
    }

    private function mover(string $tipo, Producto $p, int $almacenId, float $cantidad, array $ctx): Kardex
    {
        $signo = $tipo === self::INGRESO ? 1 : -1;

        /* Stock del almacén */
        $fila     = AlmacenProducto::query()->where('almacen_id', $almacenId)->where('producto_id', $p->id)->lockForUpdate()->first();
        $anterior = (float) ($fila?->stock ?? 0);
        $nuevo    = round($anterior + $signo * $cantidad, 4);

        if ($nuevo < 0) {
            throw ValidationException::withMessages([
                'productos' => "Stock insuficiente de «{$p->nombre}»: disponible {$anterior}, requerido {$cantidad}.",
            ]);
        }

        $fila
            ? $fila->update(['stock' => $nuevo])
            : AlmacenProducto::create(['almacen_id' => $almacenId, 'producto_id' => $p->id, 'stock' => $nuevo, 'stock_minimo' => 0]);

        /* Stock del lote (si el movimiento es de un lote) */
        /** @var Lote|null $lote */
        $lote  = $ctx['lote'] ?? null;
        $costo = $ctx['costo'] ?? null;

        if ($lote) {
            $existencia = ExistenciaLote::query()->where('lote_id', $lote->id)->where('almacen_id', $almacenId)->lockForUpdate()->first();
            $enLote     = round((float) ($existencia?->cantidad ?? 0) + $signo * $cantidad, 4);

            if ($enLote < 0) {
                throw ValidationException::withMessages([
                    'productos' => "El lote {$lote->codigo_lote} de «{$p->nombre}» no tiene stock suficiente.",
                ]);
            }

            $datos = ['cantidad' => $enLote] + ($tipo === self::INGRESO && $costo !== null ? ['costo_unitario' => $costo] : []);
            $existencia
                ? $existencia->update($datos)
                : ExistenciaLote::create($datos + ['lote_id' => $lote->id, 'almacen_id' => $almacenId, 'costo_unitario' => $costo ?? 0]);
        }

        /* Kardex */
        $ref = $ctx['ref'] ?? [];

        return Kardex::create([
            'empresa_id'        => $ctx['empresa_id'],
            'almacen_id'        => $almacenId,
            'producto_id'       => $p->id,
            'lote_id'           => $lote?->id,
            'user_id'           => $ctx['user_id'],
            'referencia_tipo'   => $ref['tipo'] ?? null,
            'referencia_id'     => $ref['id'] ?? null,
            'referencia_serie'  => $ref['serie'] ?? null,
            'referencia_numero' => isset($ref['numero']) ? (string) $ref['numero'] : null,
            'tipo_movimiento'   => $tipo,
            'cantidad'          => $cantidad,
            'costo_unitario'    => $costo,
            'costo_total'       => $costo !== null ? round($costo * $cantidad, 2) : null,
            'stock_anterior'    => $anterior,
            'stock_nuevo'       => $nuevo,
            'fecha_vencimiento' => $lote?->fecha_vencimiento,
            'fecha_movimiento'  => $ctx['fecha'] ?? Carbon::now(),
            'observacion'       => $ctx['obs'] ?? null,
        ]);
    }
}
