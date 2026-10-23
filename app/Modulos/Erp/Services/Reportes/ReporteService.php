<?php

namespace App\Modulos\Erp\Services\Reportes;

use App\Modulos\Erp\Models\Compra;
use App\Modulos\Erp\Models\Pago;
use App\Modulos\Erp\Models\Venta;
use App\Modulos\Nucleo\Support\Sql;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Reportes del ERP. Cada método devuelve la misma estructura para la vista genérica y el CSV:
 *   resumen   [[label, valor, tipo, tono]]          indicadores de arriba
 *   columnas  [[clave, label, tipo]]                tipo: texto|moneda|numero|cantidad|porcentaje|fecha
 *   filas     Collection<array>                      (clave '_url' opcional: enlace de la primera columna)
 *   totales   [clave => valor]                      fila de totales (opcional)
 *   grafico   ['etiquetas' => [], 'valores' => [], 'tipo' => moneda|numero] (opcional)
 *
 * SQL portable (MySQL, PostgreSQL/Supabase, SQLite): agrupar por todas las columnas no agregadas y
 * usar App\Modulos\Nucleo\Support\Sql para fechas y textos.
 */
class ReporteService
{
    /** clave => [título, descripción, ícono, grupo, filtros] */
    public const CATALOGO = [
        'ventas'            => ['Ventas por período', 'Totales por día o mes con IGV, ticket promedio y comprobantes.', 'bar-chart', 'Ventas', ['fechas', 'agrupar', 'almacen', 'vendedor']],
        'ventas-vendedor'   => ['Ventas por vendedor', 'Desempeño de cada vendedor: ventas, importe, ticket y anulaciones.', 'users', 'Ventas', ['fechas', 'almacen']],
        'metodos-pago'      => ['Cobros por método de pago', 'Efectivo, tarjeta, Yape, Plin y transferencias cobrados en el período.', 'credit-card', 'Ventas', ['fechas']],
        'productos-vendidos' => ['Productos más vendidos', 'Ranking por importe con cantidades, costo, utilidad y margen.', 'trending-up', 'Ventas', ['fechas', 'categoria', 'almacen']],
        'utilidad'          => ['Utilidad por categoría', 'Ventas netas, costo de lo vendido, utilidad bruta y margen de cada familia.', 'pie-chart', 'Rentabilidad', ['fechas']],
        'inventario'        => ['Inventario valorizado', 'Stock actual por producto valorizado al costo y al precio de venta.', 'warehouse', 'Inventario', ['almacen', 'categoria']],
        'reposicion'        => ['Productos para reponer', 'Stock bajo el mínimo, días de cobertura según ventas y cantidad sugerida de compra.', 'truck', 'Inventario', ['almacen']],
        'sin-rotacion'      => ['Productos sin rotación', 'Stock inmovilizado: productos con existencias que no se venden hace tiempo.', 'history', 'Inventario', ['almacen', 'dias']],
        'compras-proveedor' => ['Compras por proveedor', 'Compras, importe, pagado y saldo pendiente con cada proveedor.', 'building', 'Compras', ['fechas']],
        'caja'              => ['Arqueos de caja', 'Turnos cerrados: apertura, cobros, esperado, contado y diferencias por cajero.', 'wallet', 'Caja', ['fechas']],
        'registro-ventas'   => ['Registro de ventas', 'Detalle de comprobantes para contabilidad (base, IGV, total y estado), listo para exportar.', 'download', 'Contabilidad', ['fechas']],
    ];

    public function generar(string $clave, int $empresaId, array $f): array
    {
        $metodo = Str::camel($clave);

        return $this->{$metodo}($empresaId, $this->fechas($f) + $f);
    }

    /* ═════════════════════════════ Ventas ═════════════════════════════ */

    private function ventas(int $e, array $f): array
    {
        $agrupar = ($f['agrupar'] ?? 'dia') === 'mes' ? 'mes' : 'dia';
        $periodo = Sql::periodo('ventas.fecha_emision', $agrupar);

        $filas = $this->ventasBase($e, $f)
            ->selectRaw("{$periodo} AS periodo, COUNT(*) AS ventas, SUM(ventas.subtotal) AS base, SUM(ventas.total_impuestos) AS igv,
                SUM(ventas.descuento) AS descuento, SUM(ventas.total) AS total")
            ->groupByRaw($periodo)
            ->orderByRaw($periodo)
            ->get()
            ->map(fn ($r) => [
                'periodo'   => $this->etiquetaPeriodo($r->periodo, $agrupar),
                'ventas'    => (int) $r->ventas,
                'base'      => (float) $r->base,
                'igv'       => (float) $r->igv,
                'descuento' => (float) $r->descuento,
                'total'     => (float) $r->total,
                'ticket'    => $r->ventas > 0 ? round($r->total / $r->ventas, 2) : 0,
            ]);

        $anuladas = $this->ventasBase($e, $f, Venta::ANULADA)->count();
        $total = $filas->sum('total');
        $cantidad = $filas->sum('ventas');

        $porTipo = $this->ventasBase($e, $f)->selectRaw('ventas.tipo, COUNT(*) AS n, SUM(ventas.total) AS total')->groupBy('ventas.tipo')->get();
        $tipos = config('ventas.tipos_comprobante') + ['00' => 'Ticket'];

        return [
            'resumen' => array_merge([
                ['Ventas', $total, 'moneda', 'brand'],
                ['Comprobantes', $cantidad, 'numero', 'slate'],
                ['Ticket promedio', $cantidad ? round($total / $cantidad, 2) : 0, 'moneda', 'emerald'],
                ['Anuladas', $anuladas, 'numero', 'red'],
            ], $porTipo->map(fn ($t) => [($tipos[$t->tipo] ?? $t->tipo) . " ({$t->n})", (float) $t->total, 'moneda', 'slate'])->all()),
            'columnas' => [
                ['periodo', $agrupar === 'mes' ? 'Mes' : 'Día', 'texto'], ['ventas', 'Comprobantes', 'numero'],
                ['base', 'Base imponible', 'moneda'], ['igv', 'IGV', 'moneda'], ['descuento', 'Descuentos', 'moneda'],
                ['total', 'Total', 'moneda'], ['ticket', 'Ticket prom.', 'moneda'],
            ],
            'filas'   => $filas,
            'totales' => ['periodo' => 'Total', 'ventas' => $cantidad, 'base' => $filas->sum('base'), 'igv' => $filas->sum('igv'),
                'descuento' => $filas->sum('descuento'), 'total' => $total, 'ticket' => $cantidad ? round($total / $cantidad, 2) : 0],
            'grafico' => ['etiquetas' => $filas->pluck('periodo')->all(), 'valores' => $filas->pluck('total')->all(), 'tipo' => 'moneda'],
        ];
    }

    private function ventasVendedor(int $e, array $f): array
    {
        $filas = DB::table('ventas')
            ->join('users as u', 'u.id', '=', 'ventas.user_id')
            ->where('ventas.empresa_id', $e)->whereNull('ventas.deleted_at')
            ->whereBetween('ventas.fecha_emision', [$f['_desde'], $f['_hasta']])
            ->whereIn('ventas.estado', [Venta::COMPLETADA, Venta::ANULADA])
            ->when(filled($f['almacen_id'] ?? null), fn ($q) => $q->where('ventas.almacen_id', $f['almacen_id']))
            ->groupBy('u.id', 'u.name', 'u.apellido')
            ->selectRaw("u.id, u.name, u.apellido,
                SUM(CASE WHEN ventas.estado = ? THEN 1 ELSE 0 END) AS ventas,
                SUM(CASE WHEN ventas.estado = ? THEN ventas.total ELSE 0 END) AS total,
                SUM(CASE WHEN ventas.estado = ? THEN 1 ELSE 0 END) AS anuladas", [Venta::COMPLETADA, Venta::COMPLETADA, Venta::ANULADA])
            ->orderByDesc('total')
            ->get();

        $gran = (float) $filas->sum('total');
        $filas = $filas->map(fn ($r) => [
            'vendedor' => trim($r->name . ' ' . $r->apellido),
            'ventas'   => (int) $r->ventas,
            'total'    => (float) $r->total,
            'ticket'   => $r->ventas > 0 ? round($r->total / $r->ventas, 2) : 0,
            'anuladas' => (int) $r->anuladas,
            'part'     => $gran > 0 ? round($r->total / $gran * 100, 1) : 0,
        ]);

        return [
            'resumen'  => [['Ventas', $gran, 'moneda', 'brand'], ['Vendedores', $filas->count(), 'numero', 'slate'], ['Anuladas', $filas->sum('anuladas'), 'numero', 'red']],
            'columnas' => [['vendedor', 'Vendedor', 'texto'], ['ventas', 'Ventas', 'numero'], ['total', 'Importe', 'moneda'],
                ['ticket', 'Ticket prom.', 'moneda'], ['anuladas', 'Anuladas', 'numero'], ['part', 'Participación', 'porcentaje']],
            'filas'    => $filas,
            'totales'  => ['vendedor' => 'Total', 'ventas' => $filas->sum('ventas'), 'total' => $gran, 'anuladas' => $filas->sum('anuladas'), 'part' => 100],
            'grafico'  => ['etiquetas' => $filas->pluck('vendedor')->all(), 'valores' => $filas->pluck('total')->all(), 'tipo' => 'moneda'],
        ];
    }

    private function metodosPago(int $e, array $f): array
    {
        $etiquetas = config('ventas.metodos_pago');

        $filas = DB::table('pagos')
            ->where('empresa_id', $e)->whereNull('deleted_at')
            ->where('tipo', Pago::TIPO_COBRO)->where('estado', Pago::REGISTRADO)
            ->whereBetween('fecha_pago', [$f['_desde'], $f['_hasta']])
            ->groupBy('metodo_pago')
            ->selectRaw('metodo_pago, COUNT(*) AS cobros, SUM(monto) AS total')
            ->orderByDesc('total')
            ->get();

        $gran = (float) $filas->sum('total');
        $filas = $filas->map(fn ($r) => [
            'metodo' => $etiquetas[$r->metodo_pago] ?? Str::headline((string) $r->metodo_pago),
            'cobros' => (int) $r->cobros,
            'total'  => (float) $r->total,
            'part'   => $gran > 0 ? round($r->total / $gran * 100, 1) : 0,
        ]);

        return [
            'resumen'  => [['Total cobrado', $gran, 'moneda', 'brand'], ['Cobros', $filas->sum('cobros'), 'numero', 'slate']],
            'columnas' => [['metodo', 'Método', 'texto'], ['cobros', 'Cobros', 'numero'], ['total', 'Importe', 'moneda'], ['part', 'Participación', 'porcentaje']],
            'filas'    => $filas,
            'totales'  => ['metodo' => 'Total', 'cobros' => $filas->sum('cobros'), 'total' => $gran, 'part' => 100],
            'grafico'  => ['etiquetas' => $filas->pluck('metodo')->all(), 'valores' => $filas->pluck('total')->all(), 'tipo' => 'moneda'],
        ];
    }

    private function productosVendidos(int $e, array $f): array
    {
        $filas = $this->detalleVentas($e, $f)
            ->leftJoin('categorias as c', 'c.id', '=', 'p.categoria_id')
            ->leftJoin('tipo_unidades as u', 'u.id', '=', 'p.tipo_unidad_id')
            ->when(filled($f['categoria_id'] ?? null), fn ($q) => $q->where('p.categoria_id', $f['categoria_id']))
            ->groupBy('p.id', 'p.nombre', 'c.nombre', 'u.descripcion')
            ->selectRaw('p.id, p.nombre, c.nombre AS categoria, u.descripcion AS unidad, SUM(dv.cantidad) AS cantidad,
                COUNT(DISTINCT v.id) AS ventas, SUM(dv.subtotal) AS neto, SUM(dv.total) AS total, ' . $this->costoSql() . ' AS costo')
            ->orderByDesc('total')
            ->limit(200)
            ->get()
            ->map(fn ($r) => [
                '_url'      => route('productos.show', $r->id),
                'producto'  => $r->nombre,
                'categoria' => $r->categoria ?? 'Sin categoría',
                'cantidad'  => (float) $r->cantidad,
                'unidad'    => $r->unidad,
                'ventas'    => (int) $r->ventas,
                'total'     => (float) $r->total,
                'costo'     => round((float) $r->costo, 2),
                'utilidad'  => round($r->neto - $r->costo, 2),
                'margen'    => $r->neto > 0 ? round(($r->neto - $r->costo) / $r->neto * 100, 1) : null,
            ]);

        return [
            'resumen'  => [
                ['Importe vendido', $filas->sum('total'), 'moneda', 'brand'],
                ['Utilidad bruta', $filas->sum('utilidad'), 'moneda', 'emerald'],
                ['Productos distintos', $filas->count(), 'numero', 'slate'],
            ],
            'columnas' => [['producto', 'Producto', 'texto'], ['categoria', 'Categoría', 'texto'], ['cantidad', 'Cantidad', 'cantidad'],
                ['unidad', 'Unidad', 'texto'], ['ventas', 'Ventas', 'numero'], ['total', 'Importe', 'moneda'], ['costo', 'Costo', 'moneda'],
                ['utilidad', 'Utilidad', 'moneda'], ['margen', 'Margen', 'porcentaje']],
            'filas'    => $filas,
            'totales'  => ['producto' => 'Total', 'total' => $filas->sum('total'), 'costo' => $filas->sum('costo'), 'utilidad' => $filas->sum('utilidad')],
            'grafico'  => ['etiquetas' => $filas->take(10)->pluck('producto')->map(fn ($n) => Str::limit($n, 22))->all(), 'valores' => $filas->take(10)->pluck('total')->all(), 'tipo' => 'moneda'],
        ];
    }

    /* ═════════════════════════════ Rentabilidad ═════════════════════════════ */

    private function utilidad(int $e, array $f): array
    {
        $filas = $this->detalleVentas($e, $f)
            ->leftJoin('categorias as c', 'c.id', '=', 'p.categoria_id')
            ->groupBy('c.id', 'c.nombre')
            ->selectRaw('c.nombre AS categoria, SUM(dv.subtotal) AS neto, ' . $this->costoSql() . ' AS costo, SUM(dv.total_impuestos) AS igv')
            ->get();

        $neto = (float) $filas->sum('neto');
        $filas = $filas->map(fn ($r) => [
            'categoria' => $r->categoria ?? 'Sin categoría',
            'neto'      => round((float) $r->neto, 2),
            'costo'     => round((float) $r->costo, 2),
            'utilidad'  => round($r->neto - $r->costo, 2),
            'margen'    => $r->neto > 0 ? round(($r->neto - $r->costo) / $r->neto * 100, 1) : null,
            'part'      => $neto > 0 ? round($r->neto / $neto * 100, 1) : 0,
        ])->sortByDesc('utilidad')->values();

        $utilidad = $filas->sum('utilidad');

        return [
            'resumen'  => [
                ['Ventas netas (sin IGV)', $neto, 'moneda', 'brand'],
                ['Costo de lo vendido', $filas->sum('costo'), 'moneda', 'slate'],
                ['Utilidad bruta', $utilidad, 'moneda', $utilidad >= 0 ? 'emerald' : 'red'],
                ['Margen bruto', $neto > 0 ? round($utilidad / $neto * 100, 1) : 0, 'porcentaje', 'amber'],
            ],
            'columnas' => [['categoria', 'Categoría', 'texto'], ['neto', 'Ventas netas', 'moneda'], ['costo', 'Costo', 'moneda'],
                ['utilidad', 'Utilidad', 'moneda'], ['margen', 'Margen', 'porcentaje'], ['part', 'Part. en ventas', 'porcentaje']],
            'filas'    => $filas,
            'totales'  => ['categoria' => 'Total', 'neto' => $neto, 'costo' => $filas->sum('costo'), 'utilidad' => $utilidad,
                'margen' => $neto > 0 ? round($utilidad / $neto * 100, 1) : null, 'part' => 100],
            'grafico'  => ['etiquetas' => $filas->pluck('categoria')->all(), 'valores' => $filas->pluck('utilidad')->all(), 'tipo' => 'moneda'],
        ];
    }

    /* ═════════════════════════════ Inventario ═════════════════════════════ */

    private function inventario(int $e, array $f): array
    {
        $filas = DB::table('almacen_producto as ap')
            ->join('productos as p', 'p.id', '=', 'ap.producto_id')
            ->leftJoin('categorias as c', 'c.id', '=', 'p.categoria_id')
            ->leftJoin('tipo_unidades as u', 'u.id', '=', 'p.tipo_unidad_id')
            ->where('p.empresa_id', $e)->whereNull('p.deleted_at')->where('ap.stock', '>', 0)
            ->when(filled($f['almacen_id'] ?? null), fn ($q) => $q->where('ap.almacen_id', $f['almacen_id']))
            ->when(filled($f['categoria_id'] ?? null), fn ($q) => $q->where('p.categoria_id', $f['categoria_id']))
            ->groupBy('p.id', 'p.nombre', 'p.codigo', 'c.nombre', 'u.descripcion', 'p.costo_unitario', 'p.precio_unitario')
            ->selectRaw('p.id, p.nombre, p.codigo, c.nombre AS categoria, u.descripcion AS unidad, p.costo_unitario, p.precio_unitario, SUM(ap.stock) AS stock')
            ->orderBy('p.nombre')
            ->get()
            ->map(fn ($r) => [
                '_url'        => route('productos.show', $r->id),
                'producto'    => $r->nombre,
                'categoria'   => $r->categoria ?? 'Sin categoría',
                'stock'       => (float) $r->stock,
                'unidad'      => $r->unidad,
                'costo'       => (float) $r->costo_unitario,
                'valor_costo' => round($r->stock * $r->costo_unitario, 2),
                'precio'      => (float) $r->precio_unitario,
                'valor_venta' => round($r->stock * $r->precio_unitario, 2),
            ]);

        $porCategoria = $filas->groupBy('categoria')->map->sum('valor_costo')->sortDesc();

        return [
            'resumen'  => [
                ['Valor al costo', $filas->sum('valor_costo'), 'moneda', 'brand'],
                ['Valor a precio de venta', $filas->sum('valor_venta'), 'moneda', 'emerald'],
                ['Ganancia potencial', $filas->sum('valor_venta') - $filas->sum('valor_costo'), 'moneda', 'amber'],
                ['Productos con stock', $filas->count(), 'numero', 'slate'],
            ],
            'columnas' => [['producto', 'Producto', 'texto'], ['categoria', 'Categoría', 'texto'], ['stock', 'Stock', 'cantidad'], ['unidad', 'Unidad', 'texto'],
                ['costo', 'Costo unit.', 'moneda'], ['valor_costo', 'Valor costo', 'moneda'], ['precio', 'Precio', 'moneda'], ['valor_venta', 'Valor venta', 'moneda']],
            'filas'    => $filas,
            'totales'  => ['producto' => 'Total', 'valor_costo' => $filas->sum('valor_costo'), 'valor_venta' => $filas->sum('valor_venta')],
            'grafico'  => ['etiquetas' => $porCategoria->keys()->take(12)->all(), 'valores' => $porCategoria->values()->take(12)->all(), 'tipo' => 'moneda'],
        ];
    }

    private function reposicion(int $e, array $f): array
    {
        $dias = 30;
        $vendido = DB::table('detalle_ventas as dv')
            ->join('ventas as v', 'v.id', '=', 'dv.venta_id')
            ->where('v.empresa_id', $e)->where('v.estado', Venta::COMPLETADA)->whereNull('v.deleted_at')->whereNull('dv.deleted_at')
            ->where('v.fecha_emision', '>=', now()->subDays($dias))
            ->groupBy('dv.producto_id', 'v.almacen_id')
            ->selectRaw('dv.producto_id, v.almacen_id, SUM(dv.cantidad) AS cantidad')
            ->get()
            ->keyBy(fn ($r) => $r->producto_id . '-' . $r->almacen_id);

        $filas = DB::table('almacen_producto as ap')
            ->join('productos as p', 'p.id', '=', 'ap.producto_id')
            ->join('almacenes as a', 'a.id', '=', 'ap.almacen_id')
            ->leftJoin('tipo_unidades as u', 'u.id', '=', 'p.tipo_unidad_id')
            ->where('p.empresa_id', $e)->where('p.activo', true)->where('p.es_servicio', false)->whereNull('p.deleted_at')
            ->where('ap.stock_minimo', '>', 0)->whereColumn('ap.stock', '<=', 'ap.stock_minimo')
            ->when(filled($f['almacen_id'] ?? null), fn ($q) => $q->where('ap.almacen_id', $f['almacen_id']))
            ->orderBy('p.nombre')
            ->get(['p.id', 'p.nombre', 'p.costo_unitario', 'a.id AS almacen_id', 'a.nombre AS almacen', 'u.descripcion AS unidad', 'ap.stock', 'ap.stock_minimo'])
            ->map(function ($r) use ($vendido, $dias) {
                $venta = (float) ($vendido[$r->id . '-' . $r->almacen_id]->cantidad ?? 0);
                $diario = $venta / $dias;
                // Reponer hasta 2× el mínimo o 15 días de venta, lo que sea mayor
                $objetivo = max($r->stock_minimo * 2, $diario * 15);
                $sugerido = max(ceil($objetivo - $r->stock), 0);

                return [
                    '_url'      => route('productos.show', $r->id),
                    'producto'  => $r->nombre,
                    'almacen'   => $r->almacen,
                    'stock'     => (float) $r->stock,
                    'minimo'    => (float) $r->stock_minimo,
                    'vendido'   => $venta,
                    'cobertura' => $diario > 0 ? round($r->stock / $diario, 1) : null,
                    'sugerido'  => $sugerido,
                    'unidad'    => $r->unidad,
                    'inversion' => round($sugerido * $r->costo_unitario, 2),
                ];
            })
            ->sortBy(fn ($r) => $r['cobertura'] ?? PHP_INT_MAX)->values();

        return [
            'resumen'  => [
                ['Productos por reponer', $filas->count(), 'numero', 'amber'],
                ['Agotados', $filas->where('stock', '<=', 0)->count(), 'numero', 'red'],
                ['Inversión sugerida', $filas->sum('inversion'), 'moneda', 'brand'],
            ],
            'columnas' => [['producto', 'Producto', 'texto'], ['almacen', 'Almacén', 'texto'], ['stock', 'Stock', 'cantidad'], ['minimo', 'Mínimo', 'cantidad'],
                ['vendido', "Vendido {$dias} días", 'cantidad'], ['cobertura', 'Días de cobertura', 'numero'], ['sugerido', 'Comprar', 'cantidad'],
                ['unidad', 'Unidad', 'texto'], ['inversion', 'Inversión', 'moneda']],
            'filas'    => $filas,
            'totales'  => ['producto' => 'Total', 'inversion' => $filas->sum('inversion')],
        ];
    }

    private function sinRotacion(int $e, array $f): array
    {
        $dias = max((int) ($f['dias'] ?? 60), 1);

        $ultimas = DB::table('detalle_ventas as dv')
            ->join('ventas as v', 'v.id', '=', 'dv.venta_id')
            ->where('v.empresa_id', $e)->where('v.estado', Venta::COMPLETADA)->whereNull('v.deleted_at')
            ->groupBy('dv.producto_id')
            ->selectRaw('dv.producto_id, MAX(v.fecha_emision) AS ultima')
            ->pluck('ultima', 'producto_id');

        $filas = DB::table('almacen_producto as ap')
            ->join('productos as p', 'p.id', '=', 'ap.producto_id')
            ->leftJoin('tipo_unidades as u', 'u.id', '=', 'p.tipo_unidad_id')
            ->where('p.empresa_id', $e)->where('p.es_servicio', false)->whereNull('p.deleted_at')->where('ap.stock', '>', 0)
            ->when(filled($f['almacen_id'] ?? null), fn ($q) => $q->where('ap.almacen_id', $f['almacen_id']))
            ->groupBy('p.id', 'p.nombre', 'p.costo_unitario', 'p.created_at', 'u.descripcion')
            ->selectRaw('p.id, p.nombre, p.costo_unitario, p.created_at, u.descripcion AS unidad, SUM(ap.stock) AS stock')
            ->get()
            ->map(function ($r) use ($ultimas) {
                $ultima = isset($ultimas[$r->id]) ? Carbon::parse($ultimas[$r->id]) : null;
                $desde = $ultima ?? Carbon::parse($r->created_at);

                return [
                    '_url'     => route('productos.show', $r->id),
                    'producto' => $r->nombre,
                    'stock'    => (float) $r->stock,
                    'unidad'   => $r->unidad,
                    'valor'    => round($r->stock * $r->costo_unitario, 2),
                    'ultima'   => $ultima?->format('Y-m-d'),
                    'dias'     => (int) $desde->diffInDays(now()),
                ];
            })
            ->filter(fn ($r) => $r['dias'] >= $dias)
            ->sortByDesc('valor')->values();

        return [
            'resumen'  => [
                ['Productos sin rotación', $filas->count(), 'numero', 'amber'],
                ['Valor inmovilizado', $filas->sum('valor'), 'moneda', 'red'],
                ['Umbral', $dias, 'numero', 'slate'],
            ],
            'columnas' => [['producto', 'Producto', 'texto'], ['stock', 'Stock', 'cantidad'], ['unidad', 'Unidad', 'texto'],
                ['valor', 'Valor al costo', 'moneda'], ['ultima', 'Última venta', 'fecha'], ['dias', 'Días sin venta', 'numero']],
            'filas'    => $filas,
            'totales'  => ['producto' => 'Total', 'valor' => $filas->sum('valor')],
        ];
    }

    /* ═════════════════════════════ Compras y caja ═════════════════════════════ */

    private function comprasProveedor(int $e, array $f): array
    {
        $pagado = '(select COALESCE(sum(pg.monto), 0) from pagos pg where pg.compra_id = compras.id and pg.estado = ? and pg.deleted_at is null)';

        $filas = DB::table('compras')
            ->join('proveedores as pr', 'pr.id', '=', 'compras.proveedor_id')
            ->where('compras.empresa_id', $e)->where('compras.estado', Compra::REGISTRADA)->whereNull('compras.deleted_at')
            ->whereBetween('compras.fecha_emision', [$f['_desde'], $f['_hasta']])
            ->groupBy('pr.id', 'pr.razon_social', 'pr.numero_documento')
            ->selectRaw("pr.razon_social, pr.numero_documento, COUNT(*) AS compras, SUM(compras.total) AS total, SUM({$pagado}) AS pagado, MAX(compras.fecha_emision) AS ultima", [Pago::REGISTRADO])
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'proveedor' => $r->razon_social,
                'ruc'       => $r->numero_documento,
                'compras'   => (int) $r->compras,
                'total'     => (float) $r->total,
                'pagado'    => (float) $r->pagado,
                'saldo'     => round($r->total - $r->pagado, 2),
                'ultima'    => Carbon::parse($r->ultima)->format('Y-m-d'),
            ]);

        return [
            'resumen'  => [
                ['Total comprado', $filas->sum('total'), 'moneda', 'brand'],
                ['Pagado', $filas->sum('pagado'), 'moneda', 'emerald'],
                ['Saldo por pagar', $filas->sum('saldo'), 'moneda', 'red'],
                ['Proveedores', $filas->count(), 'numero', 'slate'],
            ],
            'columnas' => [['proveedor', 'Proveedor', 'texto'], ['ruc', 'RUC', 'texto'], ['compras', 'Compras', 'numero'], ['total', 'Total', 'moneda'],
                ['pagado', 'Pagado', 'moneda'], ['saldo', 'Saldo', 'moneda'], ['ultima', 'Última compra', 'fecha']],
            'filas'    => $filas,
            'totales'  => ['proveedor' => 'Total', 'compras' => $filas->sum('compras'), 'total' => $filas->sum('total'), 'pagado' => $filas->sum('pagado'), 'saldo' => $filas->sum('saldo')],
            'grafico'  => ['etiquetas' => $filas->take(10)->pluck('proveedor')->map(fn ($n) => Str::limit($n, 22))->all(), 'valores' => $filas->take(10)->pluck('total')->all(), 'tipo' => 'moneda'],
        ];
    }

    private function caja(int $e, array $f): array
    {
        $cobrado = '(select COALESCE(sum(pg.monto), 0) from pagos pg where pg.caja_sesion_id = s.id and pg.tipo = ? and pg.estado = ? and pg.deleted_at is null)';

        $filas = DB::table('caja_sesiones as s')
            ->join('cajas as c', 'c.id', '=', 's.caja_id')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->where('s.empresa_id', $e)->where('s.estado', 'cerrada')
            ->whereBetween('s.fecha_apertura', [$f['_desde'], $f['_hasta']])
            ->orderByDesc('s.fecha_apertura')
            ->selectRaw("s.id, c.nombre AS caja, u.name, u.apellido, s.fecha_apertura, s.fecha_cierre, s.monto_apertura, s.monto_esperado,
                s.monto_contado, s.diferencia, {$cobrado} AS cobrado", [Pago::TIPO_COBRO, Pago::REGISTRADO])
            ->get()
            ->map(fn ($r) => [
                '_url'      => route('caja.turnos.show', $r->id),
                'turno'     => Carbon::parse($r->fecha_apertura)->format('d/m/Y H:i'),
                'caja'      => $r->caja,
                'cajero'    => trim($r->name . ' ' . $r->apellido),
                'apertura'  => (float) $r->monto_apertura,
                'cobrado'   => (float) $r->cobrado,
                'esperado'  => (float) $r->monto_esperado,
                'contado'   => (float) $r->monto_contado,
                'diferencia' => (float) $r->diferencia,
            ]);

        return [
            'resumen'  => [
                ['Turnos cerrados', $filas->count(), 'numero', 'slate'],
                ['Total cobrado', $filas->sum('cobrado'), 'moneda', 'brand'],
                ['Sobrantes', $filas->where('diferencia', '>', 0)->sum('diferencia'), 'moneda', 'amber'],
                ['Faltantes', abs($filas->where('diferencia', '<', 0)->sum('diferencia')), 'moneda', 'red'],
            ],
            'columnas' => [['turno', 'Apertura', 'texto'], ['caja', 'Caja', 'texto'], ['cajero', 'Cajero', 'texto'], ['apertura', 'Monto inicial', 'moneda'],
                ['cobrado', 'Cobrado', 'moneda'], ['esperado', 'Esperado', 'moneda'], ['contado', 'Contado', 'moneda'], ['diferencia', 'Diferencia', 'moneda']],
            'filas'    => $filas,
            'totales'  => ['turno' => 'Total', 'cobrado' => $filas->sum('cobrado'), 'diferencia' => $filas->sum('diferencia')],
        ];
    }

    /* ═════════════════════════════ Contabilidad ═════════════════════════════ */

    private function registroVentas(int $e, array $f): array
    {
        $tipos = config('ventas.tipos_comprobante') + ['00' => 'Ticket'];

        $filas = DB::table('ventas')
            ->leftJoin('clientes as c', 'c.id', '=', 'ventas.cliente_id')
            ->leftJoin('tipo_documentos as td', 'td.id', '=', 'c.tipo_documento_id')
            ->where('ventas.empresa_id', $e)->whereNull('ventas.deleted_at')
            ->whereIn('ventas.estado', [Venta::COMPLETADA, Venta::ANULADA])
            ->whereBetween('ventas.fecha_emision', [$f['_desde'], $f['_hasta']])
            ->orderBy('ventas.fecha_emision')->orderBy('ventas.serie')->orderBy('ventas.numero')
            ->get(['ventas.id', 'ventas.fecha_emision', 'ventas.tipo', 'ventas.serie', 'ventas.numero', 'ventas.subtotal', 'ventas.total_impuestos',
                'ventas.total', 'ventas.estado', 'td.codigo AS doc_tipo', 'c.numero_documento', 'c.nombre', 'c.apellido'])
            ->map(function ($r) use ($tipos) {
                $anulada = $r->estado === Venta::ANULADA;

                return [
                    '_url'      => route('ventas.show', $r->id),
                    'fecha'     => Carbon::parse($r->fecha_emision)->format('Y-m-d'),
                    'tipo'      => $tipos[$r->tipo] ?? $r->tipo,
                    'numero'    => $r->serie . '-' . str_pad((string) $r->numero, 8, '0', STR_PAD_LEFT),
                    'doc_tipo'  => $r->doc_tipo ?? '0',
                    'documento' => $r->numero_documento ?? '-',
                    'cliente'   => trim(($r->nombre ?? 'Clientes varios') . ' ' . ($r->apellido ?? '')),
                    'base'      => $anulada ? 0 : (float) $r->subtotal,
                    'igv'       => $anulada ? 0 : (float) $r->total_impuestos,
                    'total'     => $anulada ? 0 : (float) $r->total,
                    'estado'    => $anulada ? 'Anulada' : 'Emitida',
                ];
            });

        return [
            'resumen'  => [
                ['Base imponible', $filas->sum('base'), 'moneda', 'brand'],
                ['IGV', $filas->sum('igv'), 'moneda', 'amber'],
                ['Total', $filas->sum('total'), 'moneda', 'emerald'],
                ['Comprobantes', $filas->count(), 'numero', 'slate'],
            ],
            'columnas' => [['fecha', 'Fecha', 'fecha'], ['tipo', 'Comprobante', 'texto'], ['numero', 'Serie-Número', 'texto'], ['doc_tipo', 'Tipo doc.', 'texto'],
                ['documento', 'N.º doc.', 'texto'], ['cliente', 'Cliente', 'texto'], ['base', 'Base imponible', 'moneda'], ['igv', 'IGV', 'moneda'],
                ['total', 'Total', 'moneda'], ['estado', 'Estado', 'texto']],
            'filas'    => $filas,
            'totales'  => ['fecha' => 'Total', 'base' => $filas->sum('base'), 'igv' => $filas->sum('igv'), 'total' => $filas->sum('total')],
        ];
    }

    /* ═════════════════════════════ Auxiliares ═════════════════════════════ */

    /** Rango de fechas (por defecto, el mes actual) como inicio y fin de día */
    private function fechas(array $f): array
    {
        $desde = filled($f['desde'] ?? null) ? Carbon::parse($f['desde']) : now()->startOfMonth();
        $hasta = filled($f['hasta'] ?? null) ? Carbon::parse($f['hasta']) : now();

        return ['_desde' => $desde->copy()->startOfDay(), '_hasta' => $hasta->copy()->endOfDay()];
    }

    private function ventasBase(int $e, array $f, string $estado = Venta::COMPLETADA): Builder
    {
        return DB::table('ventas')
            ->where('ventas.empresa_id', $e)->where('ventas.estado', $estado)->whereNull('ventas.deleted_at')
            ->whereBetween('ventas.fecha_emision', [$f['_desde'], $f['_hasta']])
            ->when(filled($f['almacen_id'] ?? null), fn ($q) => $q->where('ventas.almacen_id', $f['almacen_id']))
            ->when(filled($f['user_id'] ?? null), fn ($q) => $q->where('ventas.user_id', $f['user_id']));
    }

    /** Detalle de ventas completadas del período con su producto (alias dv, v, p) */
    private function detalleVentas(int $e, array $f): Builder
    {
        return DB::table('detalle_ventas as dv')
            ->join('ventas as v', 'v.id', '=', 'dv.venta_id')
            ->join('productos as p', 'p.id', '=', 'dv.producto_id')
            ->where('v.empresa_id', $e)->where('v.estado', Venta::COMPLETADA)
            ->whereNull('v.deleted_at')->whereNull('dv.deleted_at')
            ->whereBetween('v.fecha_emision', [$f['_desde'], $f['_hasta']])
            ->when(filled($f['almacen_id'] ?? null), fn ($q) => $q->where('v.almacen_id', $f['almacen_id']));
    }

    /** Costo de lo vendido: el guardado al vender o, en ventas antiguas, el costo actual del producto */
    private function costoSql(): string
    {
        return 'COALESCE(SUM(dv.cantidad * COALESCE(NULLIF(dv.costo_unitario, 0), p.costo_unitario)), 0)';
    }

    private function etiquetaPeriodo(string $periodo, string $agrupar): string
    {
        return $agrupar === 'mes'
            ? Str::ucfirst(Carbon::parse($periodo . '-01')->translatedFormat('M Y'))
            : Carbon::parse($periodo)->format('d/m');
    }
}
