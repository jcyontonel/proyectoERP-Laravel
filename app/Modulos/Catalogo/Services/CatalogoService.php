<?php

namespace App\Modulos\Catalogo\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Uso de los catálogos comunes a todas las empresas (impuestos y unidades de medida).
 * Una empresa solo modifica o elimina un registro del catálogo si ninguna OTRA empresa lo usa:
 * así un cambio en una empresa no altera los productos ni los documentos de otra.
 */
class CatalogoService
{
    /** Tablas que referencian el catálogo: [tabla, columna de la empresa o join al documento] */
    private const USOS = [
        'productos'                    => ['productos.empresa_id', null],
        'unidades_conversion'          => ['unidades_conversion.empresa_id', null],
        'detalle_ventas'               => ['ventas.empresa_id', ['ventas', 'ventas.id', 'detalle_ventas.venta_id']],
        'detalle_compras'              => ['compras.empresa_id', ['compras', 'compras.id', 'detalle_compras.compra_id']],
        'detalle_notas_credito_debito' => ['notas_credito_debito.empresa_id', ['notas_credito_debito', 'notas_credito_debito.id', 'detalle_notas_credito_debito.nota_id']],
    ];

    /**
     * Cuántas filas usan cada id, por empresa: [id => [empresa_id => filas]].
     *
     * @param  string  $columna  impuesto_id | tipo_unidad_id
     * @return Collection<int, Collection<int, int>>
     */
    public function uso(string $columna, ?array $ids = null): Collection
    {
        $filas = collect();

        foreach (self::USOS as $tabla => [$empresa, $join]) {
            $columnas = $this->columnas($tabla, $columna);

            foreach ($columnas as $col) {
                $q = DB::table($tabla)
                    ->when($join, fn ($q) => $q->join($join[0], $join[1], '=', $join[2]))
                    ->whereNotNull("{$tabla}.{$col}")
                    ->when($ids, fn ($q) => $q->whereIn("{$tabla}.{$col}", $ids))
                    ->groupBy("{$tabla}.{$col}", $empresa)
                    ->selectRaw("{$tabla}.{$col} as item, {$empresa} as empresa, count(*) as filas");

                $filas = $filas->concat($q->get());
            }
        }

        return $filas->groupBy('item')->map(fn ($grupo) => $grupo->groupBy('empresa')->map(fn ($g) => (int) $g->sum('filas')));
    }

    /** ¿Lo usa alguna empresa distinta de $empresaId? */
    public function usadoPorOtras(string $columna, int $id, int $empresaId): bool
    {
        return ($this->uso($columna, [$id])->get($id) ?? collect())->keys()->contains(fn ($e) => (int) $e !== $empresaId);
    }

    /** ¿Lo usa alguien? (para eliminar: la FK lo impediría) */
    public function enUso(string $columna, int $id): bool
    {
        return $this->uso($columna, [$id])->has($id);
    }

    /** Columnas de la tabla que apuntan al catálogo (unidades_conversion tiene origen y destino) */
    private function columnas(string $tabla, string $columna): array
    {
        if ($tabla === 'unidades_conversion') {
            return $columna === 'tipo_unidad_id' ? ['unidad_origen_id', 'unidad_destino_id'] : [];
        }

        return [$columna];
    }
}
