<?php

namespace App\Modulos\Erp\Services;

use App\Modulos\Erp\Models\Compra;
use App\Modulos\Erp\Models\Pago;
use App\Modulos\Nucleo\Models\User;
use App\Modulos\Erp\Models\Venta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cuentas por cobrar (ventas al crédito) y por pagar (compras con saldo).
 *  - Cada cobro/pago es una fila en `pagos` (tipo cobro|pago) ligada a la venta o compra.
 *  - El efectivo queda en el turno de caja abierto del usuario (y entra al arqueo).
 *  - Un "abono" global de un cliente/proveedor se reparte entre sus documentos, el más antiguo primero.
 */
class CuentasService
{
    public const COBRAR = 'cobrar';
    public const PAGAR  = 'pagar';

    /** Tramos de antigüedad de la deuda (días de atraso) */
    public const TRAMOS = ['al_dia' => 'Al día', '1_30' => '1–30 días', '31_60' => '31–60 días', '61_90' => '61–90 días', 'mas_90' => 'Más de 90 días'];

    public function __construct(private CajaService $cajas)
    {
    }

    /* ═════════════════════════════ Consultas ═════════════════════════════ */

    /** Documentos con saldo: ventas al crédito (cobrar) o compras registradas (pagar) */
    public function pendientes(string $modo, int $empresaId): Builder
    {
        [$clase, $tabla, $fk] = $this->modelo($modo);
        $pagado = "(select COALESCE(sum(pg.monto), 0) from pagos pg where pg.{$fk} = {$tabla}.id and pg.estado = ? and pg.deleted_at is null)";

        return $clase::query()
            ->deEmpresa($empresaId)
            ->when($modo === self::COBRAR,
                fn ($q) => $q->where('ventas.estado', Venta::COMPLETADA)->where('ventas.condicion_pago', Venta::CREDITO),
                fn ($q) => $q->where('compras.estado', Compra::REGISTRADA))
            ->whereRaw("{$tabla}.total > {$pagado}", [Pago::REGISTRADO])
            ->withSum(['pagosRegistrados as pagado'], 'monto');
    }

    /**
     * Resumen de una colección de documentos pendientes (con 'pagado' cargado).
     *
     * @return array{total: float, vencido: float, por_vencer: float, documentos: int, terceros: int, tramos: array<string, float>}
     */
    public function resumen(Collection $docs, string $modo): array
    {
        $tramos = array_fill_keys(array_keys(self::TRAMOS), 0.0);
        foreach ($docs as $d) {
            $tramos[$this->tramo($d->diasVencido())] += $d->saldo();
        }

        $fk = $modo === self::COBRAR ? 'cliente_id' : 'proveedor_id';

        return [
            'total'      => round($docs->sum(fn ($d) => $d->saldo()), 2),
            'vencido'    => round($docs->filter(fn ($d) => $d->estaVencida())->sum(fn ($d) => $d->saldo()), 2),
            'por_vencer' => round($docs->filter(fn ($d) => $d->fecha_vencimiento && ! $d->estaVencida()
                && $d->fecha_vencimiento->lte(today()->addDays(7)))->sum(fn ($d) => $d->saldo()), 2),
            'documentos' => $docs->count(),
            'terceros'   => $docs->pluck($fk)->filter()->unique()->count(),
            'tramos'     => array_map(fn ($v) => round($v, 2), $tramos),
        ];
    }

    public function tramo(int $diasVencido): string
    {
        return match (true) {
            $diasVencido <= 0  => 'al_dia',
            $diasVencido <= 30 => '1_30',
            $diasVencido <= 60 => '31_60',
            $diasVencido <= 90 => '61_90',
            default            => 'mas_90',
        };
    }

    /* ═════════════════════════════ Operaciones ═════════════════════════════ */

    /** Cobro (venta) o pago (compra) de un documento, total o parcial */
    public function registrar(Venta|Compra $documento, array $d, User $user): Pago
    {
        return DB::transaction(function () use ($documento, $d, $user) {
            $doc = $documento::query()->lockForUpdate()->findOrFail($documento->id);
            $esVenta = $doc instanceof Venta;

            if ($esVenta ? ! $doc->esCompletada() : ! $doc->esRegistrada()) {
                throw ValidationException::withMessages(['monto' => 'El documento está anulado o no está emitido.']);
            }

            $saldo = $doc->saldo();
            $monto = round((float) $d['monto'], 2);

            if ($saldo <= 0) {
                throw ValidationException::withMessages(['monto' => 'Este documento ya no tiene saldo pendiente.']);
            }
            if ($monto <= 0 || $monto > $saldo) {
                throw ValidationException::withMessages(['monto' => 'El monto debe estar entre S/ 0.01 y el saldo pendiente (S/ ' . number_format($saldo, 2) . ').']);
            }

            $sesion = $this->sesionCaja($doc->empresa_id, $user, $d['metodo_pago'], $esVenta);

            return Pago::create([
                'empresa_id'     => $doc->empresa_id,
                'venta_id'       => $esVenta ? $doc->id : null,
                'compra_id'      => $esVenta ? null : $doc->id,
                'caja_sesion_id' => $sesion?->id,
                'user_id'        => $user->id,
                'tipo'           => $esVenta ? Pago::TIPO_COBRO : Pago::TIPO_PAGO,
                'metodo_pago'    => $d['metodo_pago'],
                'monto'          => $monto,
                'moneda'         => $doc->moneda ?? 'PEN',
                'referencia'     => $d['referencia'] ?? null,
                'fecha_pago'     => Carbon::parse($d['fecha_pago'] ?? now())->setTimeFrom(now()),
                'estado'         => Pago::REGISTRADO,
                'observacion'    => $d['observacion'] ?? null,
            ]);
        });
    }

    /**
     * Abono global de un cliente o proveedor: se reparte entre sus documentos pendientes,
     * primero el que vence (o se emitió) antes. Devuelve los pagos creados.
     *
     * @param  Collection<int, Venta|Compra>  $documentos  pendientes del tercero
     * @return Collection<int, Pago>
     */
    public function abonar(Collection $documentos, array $d, User $user): Collection
    {
        return DB::transaction(function () use ($documentos, $d, $user) {
            $ordenados = $documentos->sortBy(fn ($doc) => ($doc->fecha_vencimiento ?? $doc->fecha_emision)->format('Y-m-d') . str_pad((string) $doc->id, 10, '0', STR_PAD_LEFT))->values();
            $deuda = round($ordenados->sum(fn ($doc) => $doc->saldo()), 2);
            $restante = round((float) $d['monto'], 2);

            if ($restante <= 0 || $restante > $deuda) {
                throw ValidationException::withMessages(['monto' => 'El abono debe estar entre S/ 0.01 y la deuda total (S/ ' . number_format($deuda, 2) . ').']);
            }

            $pagos = collect();
            foreach ($ordenados as $doc) {
                if ($restante <= 0) {
                    break;
                }
                $aplicar = min($restante, $doc->saldo());
                if ($aplicar <= 0) {
                    continue;
                }
                $pagos->push($this->registrar($doc, ['monto' => $aplicar] + $d + ['observacion' => 'Abono repartido'], $user));
                $restante = round($restante - $aplicar, 2);
            }

            return $pagos;
        });
    }

    /** Anula un cobro o pago (no si es de un turno de caja ya cerrado) */
    public function anular(Pago $pago, User $user): void
    {
        DB::transaction(function () use ($pago, $user) {
            $p = Pago::query()->lockForUpdate()->with('sesion')->findOrFail($pago->id);

            if ($p->estado !== Pago::REGISTRADO) {
                throw ValidationException::withMessages(['pago' => 'El movimiento ya estaba anulado.']);
            }
            if ($p->sesion && ! $p->sesion->estaAbierta()) {
                throw ValidationException::withMessages(['pago' => 'Pertenece a un turno de caja ya cerrado: regístralo como movimiento de caja en el turno actual.']);
            }

            $p->update([
                'estado'      => Pago::ANULADO,
                'observacion' => trim(($p->observacion ? $p->observacion . "\n" : '') . "Anulado por {$user->nombre_completo} el " . now()->format('d/m/Y H:i')),
            ]);
        });
    }

    /* ═════════════════════════════ Auxiliares ═════════════════════════════ */

    /** @return array{0: class-string<Model>, 1: string, 2: string} */
    private function modelo(string $modo): array
    {
        return $modo === self::COBRAR ? [Venta::class, 'ventas', 'venta_id'] : [Compra::class, 'compras', 'compra_id'];
    }

    /**
     * El efectivo pasa por el turno de caja del usuario. Cobrar en efectivo exige caja abierta
     * (si config caja.exigir_para_vender); pagar en efectivo la usa si está abierta.
     */
    private function sesionCaja(int $empresaId, User $user, string $metodo, bool $esCobro)
    {
        $sesion = $this->cajas->sesionAbierta($empresaId, $user->id);

        if ($metodo === 'efectivo' && $esCobro && ! $sesion && config('caja.exigir_para_vender')) {
            throw ValidationException::withMessages(['metodo_pago' => 'Para recibir efectivo abre tu turno de caja (o registra el cobro con otro método).']);
        }

        // Todo cobro/pago hecho con la caja abierta queda en el turno (el arqueo solo cuenta el efectivo)
        return $sesion;
    }
}
