<?php

namespace App\Support;

use App\Models\AlmacenProducto;
use App\Models\ExistenciaLote;
use App\Models\Producto;
use Illuminate\Support\Collection;

/** Productos con stock por almacén y lotes, en formato liviano para los editores de ajustes y transferencias */
class CatalogoInventario
{
    public static function productos(int $empresaId): Collection
    {
        $stocks = AlmacenProducto::query()
            ->whereHas('producto', fn ($q) => $q->where('empresa_id', $empresaId))
            ->get(['almacen_id', 'producto_id', 'stock'])
            ->groupBy('producto_id');

        $lotes = ExistenciaLote::query()
            ->join('lotes', 'lotes.id', '=', 'existencias_lote.lote_id')
            ->where('lotes.empresa_id', $empresaId)
            ->whereNull('lotes.deleted_at')
            ->get(['existencias_lote.almacen_id', 'existencias_lote.cantidad', 'lotes.producto_id', 'lotes.codigo_lote', 'lotes.fecha_vencimiento'])
            ->groupBy('producto_id');

        return Producto::query()->deEmpresa($empresaId)->activo()->where('es_servicio', false)
            ->with('tipoUnidad:id,descripcion')
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre', 'costo_unitario', 'maneja_lotes', 'tipo_unidad_id'])
            ->map(fn (Producto $p) => [
                'id'     => $p->id,
                'nombre' => $p->nombre,
                'codigo' => (string) $p->codigo,
                'unidad' => $p->tipoUnidad?->descripcion,
                'costo'  => $p->costo_unitario,
                'lotes'  => $p->maneja_lotes,
                'stocks' => (object) ($stocks[$p->id] ?? collect())->mapWithKeys(fn ($s) => [$s->almacen_id => (float) $s->stock])->all(),
                // [{codigo, vence, stocks: {almacen_id: cantidad}}]
                'lotesStock' => ($lotes[$p->id] ?? collect())->groupBy('codigo_lote')->map(fn ($filas, $codigo) => [
                    'codigo' => $codigo,
                    'vence'  => $filas->first()->fecha_vencimiento,
                    'stocks' => (object) $filas->mapWithKeys(fn ($f) => [$f->almacen_id => (float) $f->cantidad])->all(),
                ])->values(),
            ])->values();
    }
}
