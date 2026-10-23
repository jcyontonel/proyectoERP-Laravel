<?php

namespace App\Modulos\Erp\Services\Dashboard;

use App\Modulos\Erp\Models\AlmacenProducto;
use App\Modulos\Erp\Models\Compra;
use App\Modulos\Erp\Models\Pago;
use App\Modulos\Catalogo\Models\Producto;
use App\Modulos\Nucleo\Models\User;
use App\Modulos\Erp\Models\Venta;
use App\Modulos\Erp\Services\NotificacionService;
use App\Modulos\Plataforma\Support\Modulos;
use App\Modulos\Nucleo\Support\Sql;
use App\Modulos\Catalogo\Support\Unidades;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Datos del panel de inicio. Cada bloque (widget) se muestra según los permisos del usuario:
 *
 *   ventas      ver_ventas            KPIs del día, semana, últimas ventas, métodos de pago, más vendidos
 *   caja        gestionar-caja        turno abierto y efectivo esperado
 *   inventario  gestionar-inventario  valorizado, lotes por vencer   (gestionar-productos también)
 *   stock       todos                 alertas de stock bajo (el vendedor necesita saber qué no ofrecer)
 *   finanzas    registrar-pagos + ver-reportes   cuentas por cobrar y por pagar
 *   utilidad    ver-reportes          ventas netas, costo, margen e IGV del mes
 *   compras     gestionar-compras     últimas compras
 *   sunat       emitir-notas          estado de envíos a SUNAT
 *
 * Alcance de ventas: con "ver-reportes" se ve toda la empresa; si no, solo las ventas del propio usuario.
 */
class DashboardService
{
    public function __construct(private NotificacionService $notificaciones)
    {
    }

    public function para(User $user, int $empresaId, Carbon $fecha, string $semana = 'actual'): array
    {
        $w = $this->widgets($user, $empresaId);
        $propio = ! $user->can('ver-reportes');

        $datos = [
            'widgets' => $w,
            'alcance' => $propio ? 'propio' : 'empresa',
            'fecha'   => $fecha,
            'stock'   => $this->stockBajo($empresaId),
        ];

        if ($w['ventas']) {
            $datos += [
                'kpiVentas'     => $this->kpiVentas($empresaId, $user, $fecha, $propio),
                'grafico'       => $this->semana($empresaId, $user, $fecha, $semana, $propio),
                'ultimasVentas' => $this->ultimasVentas($empresaId, $user, $propio),
                'metodosPago'   => $this->metodosPago($empresaId, $user, $fecha, $propio),
                'topProductos'  => $this->topProductos($empresaId, $user, $fecha, $propio),
            ];
        }

        if ($w['caja']) {
            $datos['caja'] = $this->caja($empresaId, $user);
        }

        if ($w['inventario']) {
            $datos['inventario'] = $this->inventario($empresaId);
            $datos['porVencer']  = $this->porVencer($empresaId);
        }

        if ($w['finanzas']) {
            $datos['cuentas'] = $this->cuentas($empresaId);
        }

        if ($w['utilidad']) {
            $datos['utilidad'] = $this->utilidadMes($empresaId, $fecha);
        }

        if ($w['compras']) {
            $datos['compras'] = $this->comprasRecientes($empresaId);
        }

        if ($w['sunat']) {
            $datos['sunat'] = $this->sunat($empresaId);
        }

        return $datos;
    }

    /** @return array<string, bool> */
    /** Qué bloques ve el usuario: por su permiso y por los módulos del plan de la empresa */
    public function widgets(User $user, ?int $empresaId = null): array
    {
        $plan = fn (string $modulo) => Modulos::incluido($modulo, $empresaId);

        return [
            'ventas'        => $user->can('ver_ventas'),
            'caja'          => $user->can('gestionar-caja'),
            'inventario'    => $user->can('gestionar-inventario') || $user->can('gestionar-productos'),
            'lotes'         => $user->can('gestionar-inventario') && $plan('lotes'),
            // El vendedor cobra (registrar-pagos) pero no ve los saldos de toda la empresa
            'finanzas'      => $user->can('registrar-pagos') && $user->can('ver-reportes') && $plan('cuentas_cobrar'),
            'cuentas_pagar' => $plan('cuentas_pagar'),
            'utilidad'      => $user->can('ver-reportes') && $plan('reportes_avanzados'),
            'compras'       => $user->can('gestionar-compras') && $plan('compras'),
            'sunat'         => $user->can('emitir-notas'),
        ];
    }

    /* ───────────────────────── Ventas ───────────────────────── */

    private function ventas(int $empresaId, User $user, bool $propio): Builder
    {
        return Venta::deEmpresa($empresaId)->completadas()->when($propio, fn ($q) => $q->where('user_id', $user->id));
    }

    private function kpiVentas(int $empresaId, User $user, Carbon $fecha, bool $propio): array
    {
        $hoy  = $this->ventas($empresaId, $user, $propio)->entre($fecha, $fecha)
            ->selectRaw('COALESCE(SUM(total), 0) AS total, COUNT(*) AS cantidad')->first();
        $ayer = (float) $this->ventas($empresaId, $user, $propio)->entre($fecha->copy()->subDay(), $fecha->copy()->subDay())->sum('total');
        $mes  = (float) $this->ventas($empresaId, $user, $propio)->entre($fecha->copy()->startOfMonth(), $fecha)->sum('total');

        $total = (float) $hoy->total;

        return [
            'total'     => $total,
            'cantidad'  => (int) $hoy->cantidad,
            'ticket'    => $hoy->cantidad > 0 ? round($total / $hoy->cantidad, 2) : 0,
            'ayer'      => $ayer,
            'variacion' => $ayer > 0 ? round(($total - $ayer) / $ayer * 100, 1) : null,
            'mes'       => $mes,
        ];
    }

    /** Barras de 7 días que terminan en $fecha (actual) o la semana previa (anterior) */
    private function semana(int $empresaId, User $user, Carbon $fecha, string $semana, bool $propio): array
    {
        $fin    = $semana === 'anterior' ? $fecha->copy()->subDays(7) : $fecha->copy();
        $inicio = $fin->copy()->subDays(6);

        $totales = $this->ventas($empresaId, $user, $propio)->entre($inicio, $fin)
            ->selectRaw(Sql::periodo('fecha_emision') . ' AS dia, SUM(total) AS total')
            ->groupByRaw(Sql::periodo('fecha_emision'))
            ->pluck('total', 'dia');

        $dias = collect(range(0, 6))->map(function (int $i) use ($inicio, $totales) {
            $d = $inicio->copy()->addDays($i);

            return [
                'label' => Str::ucfirst($d->translatedFormat('D d')) . ($d->isToday() ? ' · Hoy' : ''),
                'total' => (float) ($totales[$d->toDateString()] ?? 0),
                'hoy'   => $d->isToday(),
            ];
        });

        $rango = fn (Carbon $a, Carbon $b) => $a->translatedFormat('d M') . ' – ' . $b->translatedFormat('d M Y');

        return [
            'dias'    => $dias->all(),
            'total'   => $dias->sum('total'),
            'actual'  => $semana === 'anterior' ? 'anterior' : 'actual',
            'semanas' => [
                'actual'   => $rango($fecha->copy()->subDays(6), $fecha),
                'anterior' => $rango($fecha->copy()->subDays(13), $fecha->copy()->subDays(7)),
            ],
        ];
    }

    private function ultimasVentas(int $empresaId, User $user, bool $propio): Collection
    {
        $metodos = config('ventas.metodos_pago');

        return Venta::deEmpresa($empresaId)
            ->when($propio, fn ($q) => $q->where('user_id', $user->id))
            ->with('cliente.tipoDocumento')
            ->orderByDesc('fecha_emision')->orderByDesc('id')
            ->limit(6)
            ->get()
            ->map(fn (Venta $v) => (object) [
                'id'             => $v->id,
                'numero'         => $v->numero_completo,
                'fecha'          => $v->fecha_emision,
                'cliente_nombre' => $v->cliente?->nombre_completo ?: 'Público general',
                'cliente_doc'    => $v->cliente?->documento_etiqueta ?? '—',
                'total'          => $v->total,
                'metodo'         => $v->estado === Venta::BORRADOR ? null : $v->metodo_pago,
                'metodo_label'   => $metodos[$v->metodo_pago] ?? Str::headline((string) $v->metodo_pago),
                'estado'         => $v->estado,
            ]);
    }

    private function metodosPago(int $empresaId, User $user, Carbon $fecha, bool $propio): array
    {
        $etiquetas = config('ventas.metodos_pago');
        $iconos    = ['efectivo' => 'banknote', 'tarjeta' => 'credit-card', 'yape' => 'smartphone', 'plin' => 'smartphone', 'transferencia' => 'landmark'];

        $filas = $this->ventas($empresaId, $user, $propio)->entre($fecha, $fecha)
            ->selectRaw('metodo_pago, SUM(total) AS monto')
            ->groupBy('metodo_pago')
            ->orderByDesc('monto')
            ->get();

        $total = (float) $filas->sum('monto');

        return [
            'total'   => $total,
            'metodos' => $filas->map(fn ($f) => [
                'label' => $etiquetas[$f->metodo_pago] ?? Str::headline((string) $f->metodo_pago),
                'icono' => $iconos[$f->metodo_pago] ?? 'wallet',
                'monto' => (float) $f->monto,
                'pct'   => $total > 0 ? round($f->monto / $total * 100) : 0,
            ])->all(),
        ];
    }

    /** Los 5 más vendidos de los últimos 30 días, por importe */
    private function topProductos(int $empresaId, User $user, Carbon $fecha, bool $propio): Collection
    {
        return DB::table('detalle_ventas as dv')
            ->join('ventas as v', 'v.id', '=', 'dv.venta_id')
            ->join('productos as p', 'p.id', '=', 'dv.producto_id')
            ->leftJoin('tipo_unidades as u', 'u.id', '=', 'p.tipo_unidad_id')
            ->where('v.empresa_id', $empresaId)
            ->where('v.estado', Venta::COMPLETADA)
            ->whereNull('v.deleted_at')->whereNull('dv.deleted_at')
            ->whereBetween('v.fecha_emision', [$fecha->copy()->subDays(29)->startOfDay(), $fecha->copy()->endOfDay()])
            ->when($propio, fn ($q) => $q->where('v.user_id', $user->id))
            ->groupBy('p.id', 'p.nombre', 'u.descripcion')
            ->selectRaw('p.id, p.nombre, u.descripcion AS unidad, SUM(dv.cantidad) AS cantidad, SUM(dv.total) AS importe')
            ->orderByDesc('importe')
            ->limit(5)
            ->get();
    }

    /* ───────────────────────── Caja ───────────────────────── */

    /** Turno abierto del usuario: los cobros de ventas quedan en `pagos` del turno (VentaService) */
    private function caja(int $empresaId, User $user): array
    {
        $sesion = $this->notificaciones->cajaAbierta($empresaId, $user);

        if (! $sesion) {
            return ['abierta' => false];
        }

        return [
            'abierta'  => true,
            'caja'     => $sesion->caja?->nombre,
            'desde'    => $sesion->fecha_apertura,
            'apertura' => $sesion->monto_apertura,
            'efectivo' => $sesion->efectivoEsperado(),
        ];
    }

    /* ───────────────────────── Inventario ───────────────────────── */

    /** Filas almacén-producto en o bajo su mínimo, las más críticas primero */
    private function stockBajo(int $empresaId): array
    {
        $base = AlmacenProducto::query()
            ->join('productos as p', 'p.id', '=', 'almacen_producto.producto_id')
            ->where('p.empresa_id', $empresaId)
            ->where('p.activo', true)->where('p.es_servicio', false)->whereNull('p.deleted_at')
            ->where('almacen_producto.stock_minimo', '>', 0)
            ->whereColumn('almacen_producto.stock', '<=', 'almacen_producto.stock_minimo');

        $filas = (clone $base)
            ->with(['producto.tipoUnidad', 'almacen'])
            ->select('almacen_producto.*')
            ->orderByRaw('almacen_producto.stock / almacen_producto.stock_minimo')
            ->limit(6)
            ->get()
            ->map(fn (AlmacenProducto $s) => (object) [
                'id'      => $s->producto_id,
                'nombre'  => $s->producto?->nombre,
                'codigo'  => $s->producto?->codigo,
                'almacen' => $s->almacen?->nombre,
                'stock'   => Unidades::cantidad($s->stock),
                'minimo'  => Unidades::cantidad($s->stock_minimo),
                'unidad'  => $s->producto?->tipoUnidad?->descripcion,
                'agotado' => $s->stock <= 0,
            ]);

        return ['total' => (clone $base)->count(), 'items' => $filas];
    }

    private function inventario(int $empresaId): array
    {
        $valor = (float) DB::table('almacen_producto as ap')
            ->join('productos as p', 'p.id', '=', 'ap.producto_id')
            ->where('p.empresa_id', $empresaId)->whereNull('p.deleted_at')
            ->where('ap.stock', '>', 0)
            ->sum(DB::raw('ap.stock * p.costo_unitario'));

        return [
            'valor'     => round($valor, 2),
            'productos' => Producto::deEmpresa($empresaId)->activo()->where('es_servicio', false)->count(),
            'agotados'  => Producto::deEmpresa($empresaId)->activo()->agotados()->count(),
        ];
    }

    private function porVencer(int $empresaId): Collection
    {
        return $this->notificaciones->lotesPorVencer($empresaId)
            ->with('producto:id,nombre')
            ->withSum('existencias as cantidad', 'cantidad')
            ->orderBy('fecha_vencimiento')
            ->limit(5)
            ->get()
            ->map(fn ($l) => (object) [
                'producto_id' => $l->producto_id,
                'producto'    => $l->producto?->nombre,
                'lote'        => $l->codigo_lote,
                'vence'       => $l->fecha_vencimiento,
                'dias'        => (int) today()->diffInDays($l->fecha_vencimiento, false),
                'cantidad'    => Unidades::cantidad($l->cantidad),
            ]);
    }

    /* ───────────────────────── Finanzas ───────────────────────── */

    private function cuentas(int $empresaId): array
    {
        $pagadoVenta  = '(select COALESCE(sum(p.monto), 0) from pagos p where p.venta_id = ventas.id and p.estado = ? and p.deleted_at is null)';
        $pagadoCompra = '(select COALESCE(sum(p.monto), 0) from pagos p where p.compra_id = compras.id and p.estado = ? and p.deleted_at is null)';

        $cobrar = Venta::deEmpresa($empresaId)->completadas()->where('condicion_pago', 'credito')
            ->selectRaw("COUNT(*) AS documentos, COALESCE(SUM(ventas.total - {$pagadoVenta}), 0) AS saldo", [Pago::REGISTRADO])
            ->whereRaw("ventas.total > {$pagadoVenta}", [Pago::REGISTRADO])
            ->first();

        // CompraService registra el pago al contado como un pago más: lo pendiente es lo que tenga saldo
        $pagar = Compra::deEmpresa($empresaId)->where('estado', Compra::REGISTRADA)
            ->selectRaw("COUNT(*) AS documentos, COALESCE(SUM(compras.total - {$pagadoCompra}), 0) AS saldo", [Pago::REGISTRADO])
            ->whereRaw("compras.total > {$pagadoCompra}", [Pago::REGISTRADO])
            ->first();

        return [
            'cobrar' => [
                'saldo'      => (float) $cobrar->saldo,
                'documentos' => (int) $cobrar->documentos,
                'vencidas'   => $this->notificaciones->ventasCreditoVencidas($empresaId)->count(),
            ],
            'pagar' => [
                'saldo'      => (float) $pagar->saldo,
                'documentos' => (int) $pagar->documentos,
                'vencidas'   => $this->notificaciones->comprasCreditoVencidas($empresaId)->count(),
            ],
        ];
    }

    /** Mes en curso: ventas netas (sin IGV), costo de lo vendido, utilidad bruta, margen e IGV */
    private function utilidadMes(int $empresaId, Carbon $fecha): array
    {
        $fila = DB::table('detalle_ventas as dv')
            ->join('ventas as v', 'v.id', '=', 'dv.venta_id')
            ->join('productos as p', 'p.id', '=', 'dv.producto_id')
            ->where('v.empresa_id', $empresaId)
            ->where('v.estado', Venta::COMPLETADA)
            ->whereNull('v.deleted_at')->whereNull('dv.deleted_at')
            ->whereBetween('v.fecha_emision', [$fecha->copy()->startOfMonth(), $fecha->copy()->endOfDay()])
            // Ventas antiguas sin costo guardado usan el costo actual del producto
            ->selectRaw('COALESCE(SUM(dv.subtotal), 0) AS neto,
                COALESCE(SUM(dv.cantidad * COALESCE(NULLIF(dv.costo_unitario, 0), p.costo_unitario)), 0) AS costo,
                COALESCE(SUM(dv.total_impuestos), 0) AS igv')
            ->first();

        $neto  = round((float) $fila->neto, 2);
        $costo = round((float) $fila->costo, 2);

        return [
            'mes'      => Str::ucfirst($fecha->translatedFormat('F Y')),
            'neto'     => $neto,
            'costo'    => $costo,
            'utilidad' => round($neto - $costo, 2),
            'margen'   => $neto > 0 ? round(($neto - $costo) / $neto * 100, 1) : null,
            'igv'      => round((float) $fila->igv, 2),
        ];
    }

    /* ───────────────────────── Compras y SUNAT ───────────────────────── */

    private function comprasRecientes(int $empresaId): Collection
    {
        return Compra::deEmpresa($empresaId)
            ->with('proveedor:id,razon_social,nombre_comercial')
            ->withSum(['pagosRegistrados as pagado'], 'monto')
            ->orderByDesc('fecha_emision')->orderByDesc('id')
            ->limit(5)
            ->get();
    }

    private function sunat(int $empresaId): array
    {
        $porEstado = DB::table('documentos')->where('empresa_id', $empresaId)->whereIn('tipo', ['01', '03', '07', '08'])
            ->selectRaw('estado, COUNT(*) AS total')->groupBy('estado')->pluck('total', 'estado');

        return [
            'aceptados'  => (int) ($porEstado['aceptado'] ?? 0),
            'pendientes' => (int) (($porEstado['pendiente'] ?? 0) + ($porEstado['enviado'] ?? 0)),
            'observados' => (int) ($porEstado['observado'] ?? 0),
            'rechazados' => (int) ($porEstado['rechazado'] ?? 0),
        ];
    }
}
