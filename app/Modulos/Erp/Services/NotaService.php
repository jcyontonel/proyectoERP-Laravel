<?php

namespace App\Modulos\Erp\Services;

use App\Modulos\Suscripciones\Services\SuscripcionService;

use App\Modulos\Erp\Models\Correlativo;
use App\Modulos\Erp\Models\Devolucion;
use App\Modulos\Erp\Models\Lote;
use App\Modulos\Erp\Models\MovimientoCaja;
use App\Modulos\Erp\Models\Nota;
use App\Modulos\Erp\Models\Pago;
use App\Modulos\Nucleo\Models\User;
use App\Modulos\Erp\Models\Venta;
use App\Modulos\Erp\Support\Montos;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Notas de crédito y débito sobre facturas y boletas.
 *  - Crédito: total (anula/devuelve todo), por ítems (devolución parcial) o por monto (descuento, otros).
 *    Las devoluciones reingresan el stock (devolución física + kardex). El importe primero reduce la deuda
 *    de una venta al crédito (pago con método "nota_credito"); el resto se reembolsa (caja) o queda a favor.
 *  - Débito: por monto (intereses, aumento de valor, penalidades); se cobra al emitirla.
 *  - Consume un comprobante del plan. La serie es la del comprobante modificado (F → FC01/FD01, B → BC01/BD01).
 */
class NotaService
{
    public const METODO_APLICACION = 'nota_credito';

    public function __construct(
        private InventarioService $inventario,
        private CajaService $cajas,
        private CorrelativoService $correlativos,
    ) {
    }

    /**
     * @param  array{tipo: string, motivo_codigo: string, descripcion?: ?string, monto?: ?float, items?: array<int, float>, metodo?: ?string, observacion?: ?string}  $d
     *   items: [detalle_venta_id => cantidad] (motivo por ítems) · metodo: reembolso (crédito) o cobro (débito); "ninguno" = saldo a favor
     */
    public function emitir(Venta $venta, array $d, User $user): Nota
    {
        return DB::transaction(function () use ($venta, $d, $user) {
            $v = Venta::query()->lockForUpdate()->with(['detalles.producto', 'empresa'])->findOrFail($venta->id);
            $tipo = $d['tipo'];
            $motivo = Nota::MOTIVOS[$tipo][$d['motivo_codigo']] ?? throw ValidationException::withMessages(['motivo_codigo' => 'Elige un motivo válido.']);

            if (! $v->admiteNotas()) {
                throw ValidationException::withMessages(['venta' => 'Solo se emiten notas sobre facturas y boletas emitidas (no sobre tickets, borradores ni anuladas).']);
            }
            // Se resuelve en cada emisión: el servicio es por petición y memoriza el plan (no debe quedar retenido)
            app(SuscripcionService::class)->exigir($v->empresa_id, 'comprobantes');

            $previas = $v->notas()->emitidas()->with('detalles')->lockForUpdate()->get();
            $creditoPrevio = round($previas->where('tipo', Nota::CREDITO)->sum('total'), 2);
            $disponible = round($v->total - $creditoPrevio, 2);

            [$lineas, $devuelve] = match ($motivo[1]) {
                'total' => $this->lineasTotal($v, $previas),
                'items' => $this->lineasItems($v, $d['items'] ?? []),
                default => $this->lineasMonto($v, $d, $motivo[0]),
            };

            $total = round(array_sum(array_column($lineas, 'total')), 2);
            if ($total <= 0) {
                throw ValidationException::withMessages(['monto' => 'La nota debe tener un importe mayor a cero.']);
            }
            if ($tipo === Nota::CREDITO && $total > $disponible) {
                throw ValidationException::withMessages(['monto' => 'La nota supera lo que queda del comprobante (S/ ' . number_format($disponible, 2) . ' después de las notas anteriores).']);
            }

            /* Serie y número */
            $letra = Str::upper(Str::substr($v->serie, 0, 1));
            $corr = Correlativo::query()->deEmpresa($v->empresa_id)->where('modulo', 'nota')->where('tipo', $tipo)->activo()
                ->where('serie', 'like', $letra . '%')->orderBy('id')->lockForUpdate()->first()
                ?? throw ValidationException::withMessages(['tipo' => "No hay una serie activa de " . ($tipo === Nota::CREDITO ? 'notas de crédito' : 'notas de débito') . " que empiece con {$letra} (ej. {$letra}" . ($tipo === Nota::CREDITO ? 'C01' : 'D01') . "). Créala en Configuración → Series."]);
            $corr->emitir();

            $nota = Nota::create([
                'empresa_id'         => $v->empresa_id,
                'venta_id'           => $v->id,
                'user_id'            => $user->id,
                'tipo'               => $tipo,
                'serie'              => $corr->serie,
                'numero'             => $corr->numero,
                'fecha_emision'      => now(),
                'motivo_codigo'      => $d['motivo_codigo'],
                'motivo_descripcion' => $motivo[0],
                'moneda'             => $v->moneda ?? 'PEN',
                'subtotal'           => round(array_sum(array_column($lineas, 'subtotal')), 2),
                'total_impuestos'    => round(array_sum(array_column($lineas, 'total_impuestos')), 2),
                'total'              => $total,
                'estado'             => Nota::EMITIDA,
                'observacion'        => $d['observacion'] ?? null,
            ]);
            $nota->detalles()->createMany($lineas);

            if ($devuelve) {
                $this->reingresarStock($v, $nota, $lineas, $user);
            }

            $notas = $tipo === Nota::CREDITO
                ? $this->liquidarCredito($v, $nota, $d['metodo'] ?? 'ninguno', $user)
                : $this->cobrarDebito($v, $nota, $d['metodo'] ?? 'efectivo', $user);
            $nota->update(['observacion' => trim(($nota->observacion ? $nota->observacion . "\n" : '') . $notas)]);

            return $nota->refresh();
        });
    }

    /** Anula la nota: revierte stock, la aplicación a la deuda y el movimiento de caja */
    public function anular(Nota $nota, User $user): void
    {
        DB::transaction(function () use ($nota, $user) {
            $n = Nota::query()->lockForUpdate()->with(['devolucion.detalles.producto', 'venta'])->findOrFail($nota->id);
            if ($n->esAnulada()) {
                throw ValidationException::withMessages(['nota' => 'La nota ya estaba anulada.']);
            }

            // Stock: sale lo que había reingresado
            if ($n->devolucion && $n->devolucion->estado === 'confirmada') {
                foreach ($n->devolucion->detalles as $det) {
                    $this->inventario->egreso($det->producto, $n->devolucion->almacen_id, $det->cantidad, [
                        'empresa_id' => $n->empresa_id, 'user_id' => $user->id,
                        'ref'   => ['tipo' => 'anulacion_nota', 'id' => $n->id, 'serie' => $n->serie, 'numero' => Correlativo::formatear($n->numero)],
                        'costo' => $det->costo_unitario, 'lote' => $det->lote_id ? Lote::find($det->lote_id) : null,
                        'fecha' => now(), 'obs' => "Anulación de {$n->tipo_etiqueta} {$n->numero_completo}",
                    ]);
                }
                $n->devolucion->update(['estado' => 'anulada']);
            }

            // Aplicación a la deuda de la venta
            Pago::query()->where('venta_id', $n->venta_id)->where('metodo_pago', self::METODO_APLICACION)
                ->where('referencia', $n->numero_completo)->where('estado', Pago::REGISTRADO)->update(['estado' => Pago::ANULADO]);

            // Reembolso o cobro en caja
            $mov = MovimientoCaja::query()->where('empresa_id', $n->empresa_id)->where('concepto', $this->concepto($n))
                ->where('estado', MovimientoCaja::REGISTRADO)->with('sesion')->first();
            if ($mov) {
                if ($mov->sesion?->estaAbierta()) {
                    $this->cajas->anularMovimiento($mov);
                } else {
                    $sesion = $this->cajas->sesionAbierta($n->empresa_id, $user->id)
                        ?? throw ValidationException::withMessages(['nota' => 'El turno donde se registró el dinero ya cerró: abre tu caja para revertirlo en el turno actual.']);
                    $this->cajas->registrarMovimiento($sesion, [
                        'tipo' => $mov->tipo === MovimientoCaja::INGRESO ? MovimientoCaja::EGRESO : MovimientoCaja::INGRESO,
                        'concepto' => Str::limit('Anulación ' . $this->concepto($n), 150, ''), 'metodo_pago' => $mov->metodo_pago, 'monto' => $mov->monto,
                    ], $user->id);
                }
            }

            $n->update(['estado' => Nota::ANULADA, 'observacion' => trim(($n->observacion ? $n->observacion . "\n" : '') . "Anulada por {$user->nombre_completo} el " . now()->format('d/m/Y H:i'))]);
        });
    }

    /** Cantidad que aún se puede devolver de cada línea de la venta: [detalle_id => cantidad] */
    public function pendientesDeDevolver(Venta $venta): array
    {
        $devuelto = $this->devueltoPorProducto($venta->notas()->emitidas()->with('detalles')->get());
        $res = [];
        foreach ($venta->detalles as $det) {
            $ya = min($devuelto[$det->producto_id] ?? 0, (float) $det->cantidad);
            $devuelto[$det->producto_id] = max(($devuelto[$det->producto_id] ?? 0) - $ya, 0);
            $res[$det->id] = round((float) $det->cantidad - $ya, 4);
        }

        return $res;
    }

    /* ───────────────────────── Líneas ───────────────────────── */

    /** Toda la venta: solo si aún no tiene notas de crédito */
    private function lineasTotal(Venta $v, $previas): array
    {
        if ($previas->where('tipo', Nota::CREDITO)->isNotEmpty()) {
            throw ValidationException::withMessages(['motivo_codigo' => 'El comprobante ya tiene notas de crédito: usa «Devolución por ítem» o un motivo por monto para lo que queda.']);
        }

        return [$v->detalles->map(fn ($det) => $this->lineaDeVenta($det, (float) $det->cantidad))->all(), true];
    }

    private function lineasItems(Venta $v, array $items): array
    {
        $pendientes = $this->pendientesDeDevolver($v);
        $lineas = [];
        foreach ($v->detalles as $det) {
            $cant = round((float) ($items[$det->id] ?? 0), 4);
            if ($cant <= 0) {
                continue;
            }
            if ($cant > $pendientes[$det->id]) {
                throw ValidationException::withMessages(['items' => "De «{$det->descripcion}» solo se pueden devolver " . rtrim(rtrim(number_format($pendientes[$det->id], 4, '.', ''), '0'), '.') . '.']);
            }
            $lineas[] = $this->lineaDeVenta($det, $cant);
        }
        if (! $lineas) {
            throw ValidationException::withMessages(['items' => 'Indica la cantidad a devolver de al menos un producto.']);
        }

        return [$lineas, true];
    }

    private function lineasMonto(Venta $v, array $d, string $etiqueta): array
    {
        $monto = round((float) ($d['monto'] ?? 0), 2);
        // Si la venta no llevó IGV (exonerada/inafecta), la nota tampoco
        $igvPct = $v->total_impuestos > 0 ? (float) $v->empresa->igv : 0.0;
        [$base, $igv, $total] = Montos::desglosar($monto, $igvPct, true);

        return [[[
            'producto_id' => null, 'lote_id' => null, 'tipo_unidad_id' => null, 'impuesto_id' => null,
            'descripcion' => Str::limit(filled($d['descripcion'] ?? null) ? $d['descripcion'] : $etiqueta, 255, ''),
            'cantidad' => 1, 'precio_unitario' => $total, 'subtotal' => $base, 'total_impuestos' => $igv, 'total' => $total,
        ]], false];
    }

    /** Parte proporcional de una línea de venta (con su descuento ya aplicado) */
    private function lineaDeVenta($det, float $cant): array
    {
        $f = (float) $det->cantidad > 0 ? $cant / (float) $det->cantidad : 0;
        $sub = round($det->subtotal * $f, 2);
        $igv = round($det->total_impuestos * $f, 2);

        return [
            'producto_id' => $det->producto_id, 'lote_id' => $det->lote_id, 'tipo_unidad_id' => $det->tipo_unidad_id, 'impuesto_id' => null,
            'descripcion' => $det->descripcion, 'cantidad' => $cant, 'precio_unitario' => $det->precio_unitario,
            'subtotal' => $sub, 'total_impuestos' => $igv, 'total' => round($sub + $igv, 2),
            '_costo' => (float) $det->costo_unitario,
        ];
    }

    private function devueltoPorProducto($notas): array
    {
        $d = [];
        foreach ($notas->where('tipo', Nota::CREDITO) as $n) {
            foreach ($n->detalles->whereNotNull('producto_id') as $det) {
                $d[$det->producto_id] = ($d[$det->producto_id] ?? 0) + $det->cantidad;
            }
        }

        return $d;
    }

    /* ───────────────────────── Efectos ───────────────────────── */

    /** Devolución física: reingresa al almacén de la venta (si la empresa controla stock) */
    private function reingresarStock(Venta $v, Nota $nota, array $lineas, User $user): void
    {
        if (! $v->empresa->controlar_stock) {
            return;
        }
        $productos = $v->detalles->pluck('producto', 'producto_id');
        $conStock = array_filter($lineas, fn ($l) => $l['producto_id'] && ! ($productos[$l['producto_id']]?->es_servicio ?? true));
        if (! $conStock) {
            return;
        }

        [$serie, $numero] = $this->correlativos->emitir($v->empresa_id, 'devolucion');
        $dev = Devolucion::create([
            'empresa_id' => $v->empresa_id, 'almacen_id' => $v->almacen_id, 'venta_id' => $v->id, 'nota_id' => $nota->id,
            'user_id' => $user->id, 'serie' => $serie, 'numero' => $numero, 'tipo' => Devolucion::DE_VENTA,
            'estado' => 'confirmada', 'fecha_movimiento' => now(), 'motivo' => $nota->motivo_descripcion,
        ]);

        foreach ($conStock as $l) {
            $lote = $l['lote_id'] ? Lote::find($l['lote_id']) : null;
            $this->inventario->ingreso($productos[$l['producto_id']], $v->almacen_id, $l['cantidad'], [
                'empresa_id' => $v->empresa_id, 'user_id' => $user->id,
                'ref'   => ['tipo' => 'nota_credito', 'id' => $nota->id, 'serie' => $nota->serie, 'numero' => Correlativo::formatear($nota->numero)],
                'costo' => $l['_costo'], 'lote' => $lote, 'fecha' => now(), 'obs' => "Devolución por {$nota->numero_completo} ({$v->numero_completo})",
            ]);
            $dev->detalles()->create([
                'producto_id' => $l['producto_id'], 'lote_id' => $l['lote_id'], 'cantidad' => $l['cantidad'],
                'precio_unitario' => $l['precio_unitario'], 'costo_unitario' => $l['_costo'], 'total' => $l['total'], 'disposicion' => 'reingresar',
            ]);
        }
    }

    /** Crédito: primero baja la deuda (venta al crédito); el resto se reembolsa o queda a favor */
    private function liquidarCredito(Venta $v, Nota $nota, string $metodo, User $user): string
    {
        $aplicado = 0.0;
        if ($v->esCredito() && ($saldo = $v->saldo()) > 0) {
            $aplicado = round(min($nota->total, $saldo), 2);
            Pago::create([
                'empresa_id' => $v->empresa_id, 'venta_id' => $v->id, 'user_id' => $user->id, 'tipo' => Pago::TIPO_COBRO,
                'metodo_pago' => self::METODO_APLICACION, 'monto' => $aplicado, 'moneda' => 'PEN', 'referencia' => $nota->numero_completo,
                'fecha_pago' => now(), 'estado' => Pago::REGISTRADO, 'observacion' => "Aplicación de {$nota->tipo_etiqueta} {$nota->numero_completo}",
            ]);
        }

        $resto = round($nota->total - $aplicado, 2);
        $texto = $aplicado > 0 ? 'Aplicado a la deuda: S/ ' . number_format($aplicado, 2) . '.' : '';
        if ($resto <= 0) {
            return $texto;
        }
        if ($metodo === 'ninguno') {
            return trim($texto . ' Saldo a favor del cliente: S/ ' . number_format($resto, 2) . '.');
        }

        $this->moverCaja($nota, MovimientoCaja::EGRESO, $metodo, $resto, $user);

        return trim($texto . ' Reembolsado: S/ ' . number_format($resto, 2) . ' (' . (config('ventas.metodos_pago')[$metodo] ?? $metodo) . ').');
    }

    private function cobrarDebito(Venta $v, Nota $nota, string $metodo, User $user): string
    {
        if ($metodo === 'ninguno') {
            throw ValidationException::withMessages(['metodo' => 'Indica cómo se cobra la nota de débito.']);
        }
        $this->moverCaja($nota, MovimientoCaja::INGRESO, $metodo, $nota->total, $user);

        return 'Cobrado: S/ ' . number_format($nota->total, 2) . ' (' . (config('ventas.metodos_pago')[$metodo] ?? $metodo) . ').';
    }

    /** Efectivo: exige el turno de caja abierto. Otros métodos: quedan en el turno si hay uno abierto */
    private function moverCaja(Nota $nota, string $tipo, string $metodo, float $monto, User $user): void
    {
        $sesion = $this->cajas->sesionAbierta($nota->empresa_id, $user->id);
        if (! $sesion) {
            if ($metodo === 'efectivo') {
                throw ValidationException::withMessages(['metodo' => 'Para ' . ($tipo === MovimientoCaja::EGRESO ? 'devolver' : 'cobrar') . ' en efectivo abre tu turno de caja (o elige otro método).']);
            }

            return;
        }

        $this->cajas->registrarMovimiento($sesion, [
            'tipo' => $tipo, 'concepto' => $this->concepto($nota), 'metodo_pago' => $metodo, 'monto' => $monto,
            'observacion' => $nota->motivo_descripcion,
        ], $user->id);
    }

    private function concepto(Nota $n): string
    {
        return Str::limit("{$n->tipo_etiqueta} {$n->numero_completo}", 150, '');
    }
}
