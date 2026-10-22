<?php

namespace App\Services;

use App\Models\ExistenciaLote;
use App\Models\Lote;
use App\Models\Producto;
use Illuminate\Validation\ValidationException;

/** Lotes de producto: único por empresa + producto + código (incluye los eliminados) */
class LoteService
{
    /** Busca o crea el lote (para ingresos: compras, ajustes de ingreso) */
    public function obtenerOCrear(int $empresaId, Producto $p, string $codigo, ?string $vence = null): Lote
    {
        $lote = Lote::withTrashed()->firstOrNew([
            'empresa_id'  => $empresaId,
            'producto_id' => $p->id,
            'codigo_lote' => trim($codigo),
        ]);

        if ($lote->exists && $lote->trashed()) {
            $lote->restore();
        }
        if (! $lote->fecha_vencimiento && $vence) {
            $lote->fecha_vencimiento = $vence;
        }
        $lote->activo = true;
        $lote->save();

        return $lote;
    }

    /** Lote existente (para salidas y conteos): error claro si no existe */
    public function existente(int $empresaId, Producto $p, string $codigo): Lote
    {
        $lote = Lote::query()
            ->deEmpresa($empresaId)
            ->where('producto_id', $p->id)
            ->where('codigo_lote', trim($codigo))
            ->first();

        if (! $lote) {
            throw ValidationException::withMessages([
                'productos' => "El lote «{$codigo}» de «{$p->nombre}» no existe.",
            ]);
        }

        return $lote;
    }

    /** Cantidad del lote en un almacén */
    public function stock(Lote $lote, int $almacenId): float
    {
        return (float) (ExistenciaLote::query()->where('lote_id', $lote->id)->where('almacen_id', $almacenId)->value('cantidad') ?? 0);
    }

    /** Costo con el que entró el lote al almacén (o el costo del producto) */
    public function costo(Lote $lote, int $almacenId, Producto $p): float
    {
        $costo = ExistenciaLote::query()->where('lote_id', $lote->id)->where('almacen_id', $almacenId)->value('costo_unitario');

        return (float) ($costo ?: $p->costo_unitario);
    }
}
