<?php

namespace App\Services;

use App\Models\CajaSesion;
use App\Models\Compra;
use App\Models\Lote;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use App\Support\Modulos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Alertas del layout (campana) y burbujas del menú, calculadas desde la base.
 * Se registra como "scoped" en ErpServiceProvider: una sola vez por petición.
 */
class NotificacionService
{
    /** Alertas que el usuario puede ocultar en Preferencias: clave => título */
    public const ALERTAS = [
        'stock_bajo'  => 'Stock bajo',
        'por_vencer'  => 'Lotes por vencer',
        'cobranzas'   => 'Cobranzas vencidas',
        'pagos'       => 'Pagos a proveedores vencidos',
        'borradores'  => 'Borradores pendientes',
        'sunat'       => 'Comprobantes sin aceptar',
        'caja'        => 'Caja cerrada',
    ];

    public const DIAS_POR_VENCER = 30;

    private array $memo = [];

    /** @return array<string, int> */
    public function contadores(int $empresaId, User $user): array
    {
        return $this->memo["contadores.$empresaId"] ??= [
            'stock_bajo'          => Producto::deEmpresa($empresaId)->activo()->conStockBajo()->count(),
            'por_vencer'          => $this->lotesPorVencer($empresaId)->count(),
            'borradores'          => Venta::deEmpresa($empresaId)->where('estado', Venta::BORRADOR)->where('user_id', $user->id)->count(),
            'por_cobrar_vencidas' => $this->ventasCreditoVencidas($empresaId)->count(),
            'por_pagar_vencidas'  => $this->comprasCreditoVencidas($empresaId)->count(),
            'sunat_pendientes'    => DB::table('comprobantes_sunat')->where('empresa_id', $empresaId)
                ->whereIn('estado', ['pendiente', 'observado', 'rechazado', 'error'])->count(),
        ];
    }

    /**
     * Alertas visibles para el usuario según sus permisos.
     *
     * @return Collection<int, object{tono: string, icono: string, titulo: string, detalle: string, url: ?string}>
     */
    public function alertas(int $empresaId, User $user): Collection
    {
        $c = $this->contadores($empresaId, $user);
        $alertas = collect();

        if ($c['stock_bajo'] > 0) {
            $alertas->push($this->alerta('warning', 'alert-triangle', 'Stock bajo',
                $c['stock_bajo'] . ' ' . str('producto')->plural($c['stock_bajo']) . ' en o bajo su stock mínimo.',
                route('productos.index', ['stock' => 'bajo'])));
        }

        if ($c['por_vencer'] > 0 && $user->can('gestionar-inventario') && Modulos::incluido('lotes', $empresaId)) {
            $alertas->push($this->alerta('warning', 'calendar-clock', 'Lotes por vencer',
                "{$c['por_vencer']} " . str('lote')->plural($c['por_vencer']) . ' con stock vencen en los próximos ' . self::DIAS_POR_VENCER . ' días.',
                route('lotes.index', ['vencimiento' => 'por_vencer'])));
        }

        if ($c['por_cobrar_vencidas'] > 0 && $user->can('registrar-pagos') && Modulos::incluido('cuentas_cobrar', $empresaId)) {
            $alertas->push($this->alerta('danger', 'coins', 'Cobranzas vencidas',
                "{$c['por_cobrar_vencidas']} " . str('venta')->plural($c['por_cobrar_vencidas']) . ' al crédito pasaron su fecha de pago.',
                route('cuentas-cobrar.index', ['estado' => 'vencidas'])));
        }

        if ($c['por_pagar_vencidas'] > 0 && $user->can('cuentas-por-pagar') && Modulos::incluido('cuentas_pagar', $empresaId)) {
            $alertas->push($this->alerta('danger', 'landmark', 'Pagos a proveedores vencidos',
                "{$c['por_pagar_vencidas']} " . str('compra')->plural($c['por_pagar_vencidas']) . ' al crédito pasaron su fecha de pago.',
                route('cuentas-pagar.index', ['estado' => 'vencidas'])));
        }

        if ($c['borradores'] > 0 && $user->can('emitir-ventas')) {
            $alertas->push($this->alerta('info', 'pencil', 'Borradores pendientes',
                "Tienes {$c['borradores']} " . str('borrador')->plural($c['borradores']) . ' de venta sin emitir.',
                route('ventas.index', ['estado' => Venta::BORRADOR])));
        }

        if ($c['sunat_pendientes'] > 0 && $user->can('emitir-notas')) {
            $alertas->push($this->alerta('danger', 'upload-cloud', 'Comprobantes sin aceptar',
                "{$c['sunat_pendientes']} " . str('comprobante')->plural($c['sunat_pendientes']) . ' pendientes u observados por SUNAT.',
                route('sunat.index')));
        }

        if ($user->can('gestionar-caja') && ! $this->cajaAbierta($empresaId, $user)) {
            $alertas->push($this->alerta('neutral', 'wallet', 'Caja cerrada',
                'No tienes un turno de caja abierto. Ábrelo para registrar cobros en efectivo.',
                route('caja.index')));
        }

        // Las que el usuario ocultó en Preferencias
        $ocultas = array_intersect_key(self::ALERTAS, array_flip($user->preferencias()->alertas_ocultas ?? []));

        return $alertas->reject(fn ($a) => in_array($a->titulo, $ocultas, true))->values();
    }

    /** Turno de caja abierto por el usuario en la empresa */
    public function cajaAbierta(int $empresaId, User $user): ?CajaSesion
    {
        if (! array_key_exists("caja.$empresaId", $this->memo)) {
            $this->memo["caja.$empresaId"] = CajaSesion::deEmpresa($empresaId)->abiertas()
                ->where('user_id', $user->id)->with('caja')->latest('fecha_apertura')->first();
        }

        return $this->memo["caja.$empresaId"];
    }

    /** Lotes con existencias que vencen dentro de DIAS_POR_VENCER (incluye ya vencidos) */
    public function lotesPorVencer(int $empresaId): Builder
    {
        return Lote::deEmpresa($empresaId)->activo()
            ->whereNotNull('fecha_vencimiento')
            ->whereDate('fecha_vencimiento', '<=', now()->addDays(self::DIAS_POR_VENCER))
            ->whereHas('existencias', fn (Builder $e) => $e->where('cantidad', '>', 0));
    }

    /** Ventas al crédito con saldo y fecha de pago vencida */
    public function ventasCreditoVencidas(int $empresaId): Builder
    {
        return Venta::deEmpresa($empresaId)->completadas()
            ->where('condicion_pago', 'credito')
            ->whereDate('fecha_vencimiento', '<', today())
            ->whereRaw('ventas.total > ' . $this->pagado('venta_id', 'ventas'), [Pago::REGISTRADO]);
    }

    /** Compras al crédito con saldo y fecha de pago vencida */
    public function comprasCreditoVencidas(int $empresaId): Builder
    {
        return Compra::deEmpresa($empresaId)
            ->where('estado', Compra::REGISTRADA)
            ->where('condicion_pago', 'credito')
            ->whereDate('fecha_vencimiento', '<', today())
            ->whereRaw('compras.total > ' . $this->pagado('compra_id', 'compras'), [Pago::REGISTRADO]);
    }

    /** Subconsulta: suma de pagos vigentes del documento */
    private function pagado(string $columna, string $tabla): string
    {
        return "(select COALESCE(sum(p.monto), 0) from pagos p where p.{$columna} = {$tabla}.id and p.estado = ? and p.deleted_at is null)";
    }

    private function alerta(string $tono, string $icono, string $titulo, string $detalle, ?string $url): object
    {
        return (object) compact('tono', 'icono', 'titulo', 'detalle', 'url');
    }
}
