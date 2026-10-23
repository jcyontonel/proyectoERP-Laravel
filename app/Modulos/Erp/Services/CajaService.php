<?php

namespace App\Modulos\Erp\Services;

use App\Modulos\Erp\Models\Caja;
use App\Modulos\Erp\Models\CajaSesion;
use App\Modulos\Erp\Models\MovimientoCaja;
use App\Modulos\Erp\Models\Pago;
use App\Modulos\Erp\Models\Venta;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turnos de caja. Reglas:
 *  - una caja tiene como máximo un turno abierto, y un usuario también;
 *  - los cobros de ventas y pagos a proveedores del turno quedan en `pagos` (caja_sesion_id);
 *  - los gastos, retiros y fondos adicionales quedan en `movimientos_caja`;
 *  - al cerrar se compara el efectivo contado con el esperado (CajaSesion::efectivoEsperado).
 */
class CajaService
{
    public function sesionAbierta(int $empresaId, int $userId): ?CajaSesion
    {
        return CajaSesion::query()->deEmpresa($empresaId)->abiertas()->where('user_id', $userId)
            ->with('caja')->latest('fecha_apertura')->first();
    }

    public function abrir(int $empresaId, int $userId, array $d): CajaSesion
    {
        return DB::transaction(function () use ($empresaId, $userId, $d) {
            $caja = Caja::query()->deEmpresa($empresaId)->activo()->lockForUpdate()->find($d['caja_id']);

            if (! $caja) {
                throw ValidationException::withMessages(['caja_id' => 'La caja seleccionada no está disponible.']);
            }

            $ocupada = $caja->sesionAbierta()->with('usuario')->first();
            if ($ocupada) {
                throw ValidationException::withMessages([
                    'caja_id' => "«{$caja->nombre}» ya tiene un turno abierto por {$ocupada->usuario?->nombre_completo} desde las {$ocupada->fecha_apertura->format('H:i')}.",
                ]);
            }

            if ($this->sesionAbierta($empresaId, $userId)) {
                throw ValidationException::withMessages(['caja_id' => 'Ya tienes un turno abierto. Ciérralo antes de abrir otro.']);
            }

            return CajaSesion::create([
                'empresa_id'     => $empresaId,
                'caja_id'        => $caja->id,
                'user_id'        => $userId,
                'estado'         => CajaSesion::ABIERTA,
                'fecha_apertura' => now(),
                'monto_apertura' => round((float) $d['monto_apertura'], 2),
                'observacion'    => $d['observacion'] ?? null,
            ]);
        });
    }

    /** Ingreso o egreso manual (gasto, retiro, fondo adicional…) */
    public function registrarMovimiento(CajaSesion $sesion, array $d, int $userId): MovimientoCaja
    {
        return DB::transaction(function () use ($sesion, $d, $userId) {
            $s = CajaSesion::query()->lockForUpdate()->findOrFail($sesion->id);
            $this->exigirAbierta($s);

            $monto = round((float) $d['monto'], 2);

            if ($d['tipo'] === MovimientoCaja::EGRESO && $d['metodo_pago'] === 'efectivo' && $monto > $s->efectivoEsperado()) {
                throw ValidationException::withMessages([
                    'monto' => 'El egreso supera el efectivo disponible en caja (S/ ' . number_format($s->efectivoEsperado(), 2) . ').',
                ]);
            }

            return MovimientoCaja::create([
                'empresa_id'       => $s->empresa_id,
                'caja_sesion_id'   => $s->id,
                'user_id'          => $userId,
                'tipo'             => $d['tipo'],
                'concepto'         => trim($d['concepto']),
                'metodo_pago'      => $d['metodo_pago'],
                'monto'            => $monto,
                'fecha_movimiento' => now(),
                'estado'           => MovimientoCaja::REGISTRADO,
                'observacion'      => $d['observacion'] ?? null,
            ]);
        });
    }

    public function anularMovimiento(MovimientoCaja $movimiento): void
    {
        DB::transaction(function () use ($movimiento) {
            $m = MovimientoCaja::query()->lockForUpdate()->with('sesion')->findOrFail($movimiento->id);
            $this->exigirAbierta($m->sesion);

            if ($m->esAnulado()) {
                throw ValidationException::withMessages(['movimiento' => 'El movimiento ya estaba anulado.']);
            }

            $m->update(['estado' => MovimientoCaja::ANULADO]);
        });
    }

    /**
     * Cierra el turno con el arqueo. $d: denominaciones [valor => cantidad] o monto_contado, observacion.
     * Si la diferencia supera la tolerancia, la observación es obligatoria.
     */
    public function cerrar(CajaSesion $sesion, array $d, int $userId): CajaSesion
    {
        return DB::transaction(function () use ($sesion, $d, $userId) {
            $s = CajaSesion::query()->lockForUpdate()->findOrFail($sesion->id);
            $this->exigirAbierta($s);

            $arqueo = collect($d['denominaciones'] ?? [])
                ->map(fn ($cantidad) => (int) $cantidad)
                ->filter(fn ($cantidad) => $cantidad > 0);

            $contado = $arqueo->isNotEmpty()
                ? round($arqueo->reduce(fn ($total, $cantidad, $valor) => $total + (float) $valor * $cantidad, 0), 2)
                : round((float) ($d['monto_contado'] ?? 0), 2);

            $esperado   = $s->efectivoEsperado();
            $diferencia = round($contado - $esperado, 2);

            if (abs($diferencia) > (float) config('caja.tolerancia_diferencia') && blank($d['observacion'] ?? null)) {
                throw ValidationException::withMessages([
                    'observacion' => 'Hay una diferencia de S/ ' . number_format($diferencia, 2) . ': explica el motivo en la observación.',
                ]);
            }

            $s->update([
                'estado'         => CajaSesion::CERRADA,
                'cerrado_por'    => $userId,
                'fecha_cierre'   => now(),
                'monto_esperado' => $esperado,
                'monto_contado'  => $contado,
                'diferencia'     => $diferencia,
                'arqueo'         => $arqueo->isNotEmpty() ? $arqueo->all() : null,
                'observacion'    => trim(($s->observacion ? $s->observacion . "\n" : '') . ($d['observacion'] ?? '')) ?: null,
            ]);

            return $s;
        });
    }

    /** Totales del turno para la pantalla de caja, el cierre y la impresión */
    public function resumen(CajaSesion $sesion): array
    {
        $etiquetas = config('ventas.metodos_pago') + config('compras.metodos_pago');

        $porMetodo = fn ($filas) => $filas->map(fn ($f) => [
            'metodo' => $f->metodo_pago,
            'label'  => $etiquetas[$f->metodo_pago] ?? ucfirst((string) $f->metodo_pago),
            'monto'  => round((float) $f->monto, 2),
            'cantidad' => (int) $f->cantidad,
        ])->values()->all();

        $pagos = fn (string $tipo) => $sesion->pagos()
            ->where('estado', Pago::REGISTRADO)->where('tipo', $tipo)
            ->selectRaw('metodo_pago, SUM(monto) AS monto, COUNT(*) AS cantidad')
            ->groupBy('metodo_pago')->orderByDesc('monto')->get();

        $manuales = fn (string $tipo) => (float) $sesion->movimientos()
            ->where('estado', MovimientoCaja::REGISTRADO)->where('tipo', $tipo)->sum('monto');

        $cobros = $pagos(Pago::TIPO_COBRO);
        $pagosProv = $pagos(Pago::TIPO_PAGO);

        $ventas = Venta::query()->where('caja_sesion_id', $sesion->id);

        return [
            'apertura'       => $sesion->monto_apertura,
            'cobros'         => $porMetodo($cobros),
            'total_cobros'   => round((float) $cobros->sum('monto'), 2),
            'pagos'          => $porMetodo($pagosProv),
            'total_pagos'    => round((float) $pagosProv->sum('monto'), 2),
            'ingresos'       => round($manuales(MovimientoCaja::INGRESO), 2),
            'egresos'        => round($manuales(MovimientoCaja::EGRESO), 2),
            'efectivo'       => $sesion->estaAbierta() ? $sesion->efectivoEsperado() : (float) $sesion->monto_esperado,
            'ventas'         => (clone $ventas)->where('estado', Venta::COMPLETADA)->count(),
            'ventas_total'   => round((float) (clone $ventas)->where('estado', Venta::COMPLETADA)->sum('total'), 2),
            'ventas_anuladas' => (clone $ventas)->where('estado', Venta::ANULADA)->count(),
        ];
    }

    private function exigirAbierta(?CajaSesion $s): void
    {
        if (! $s || ! $s->estaAbierta()) {
            throw ValidationException::withMessages(['caja' => 'El turno de caja ya está cerrado.']);
        }
    }
}
