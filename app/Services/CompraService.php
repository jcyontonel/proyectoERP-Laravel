<?php

namespace App\Services;

use App\Models\AlmacenProducto;
use App\Models\Compra;
use App\Models\Empresa;
use App\Models\Lote;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CompraService
{
    /** Productos cuyo precio de venta se actualizó en la última compra: [nombre, antes, ahora] */
    public array $preciosActualizados = [];

    public function __construct(
        private InventarioService $inventario,
        private LoteService $lotes,
        private CajaService $cajas,
    ) {
    }

    /**
     * Registra una compra a un proveedor en una sola transacción:
     *  - guarda cabecera y detalle (con sus lotes si se indicaron),
     *  - ingresa el stock al almacén y deja el movimiento en kardex,
     *  - actualiza el costo del producto (último o promedio),
     *  - si es al contado, registra el pago.
     *
     * @param  array  $d  datos validados por StoreCompraRequest
     */
    public function crear(array $d, int $userId, int $empresaId): Compra
    {
        $empresa    = Empresa::findOrFail($empresaId);
        $igvPct     = (float) $empresa->igv;
        $afecto     = (bool) ($d['afecto_igv'] ?? true);
        $incluyeIgv = (bool) ($d['precios_incluyen_igv'] ?? $empresa->precios_incluyen_igv);
        $moverStock = (bool) $empresa->controlar_stock;
        $costeo     = config('compras.costeo', 'ultimo');

        $this->preciosActualizados = [];

        return DB::transaction(function () use ($d, $userId, $empresaId, $igvPct, $afecto, $incluyeIgv, $moverStock, $costeo) {
            $ahora = now();
            $fecha = Carbon::parse($d['fecha_emision'])->setTimeFrom($ahora);

            /* 1) Proveedor del almacén y de la empresa */
            $proveedor = Proveedor::query()->deEmpresa($empresaId)->activo()->find($d['proveedor_id']);
            if (! $proveedor) {
                throw ValidationException::withMessages(['proveedor_id' => 'El proveedor seleccionado no está disponible.']);
            }

            /* 2) El mismo comprobante del proveedor no se puede registrar dos veces (índice único incluye eliminados) */
            if (filled($d['serie'] ?? null) && filled($d['numero'] ?? null)) {
                $duplicada = Compra::withTrashed()
                    ->where('empresa_id', $empresaId)
                    ->where('proveedor_id', $proveedor->id)
                    ->where('tipo_comprobante', $d['tipo_comprobante'])
                    ->where('serie', $d['serie'])
                    ->where('numero', $d['numero'])
                    ->exists();

                if ($duplicada) {
                    throw ValidationException::withMessages([
                        'numero' => "Ya registraste el comprobante {$d['serie']}-{$d['numero']} de este proveedor.",
                    ]);
                }
            }

            /* 3) Productos de la empresa */
            $ids       = collect($d['productos'])->pluck('id')->unique();
            $productos = Producto::query()->deEmpresa($empresaId)->activo()->whereIn('id', $ids)->get()->keyBy('id');

            if ($productos->count() !== $ids->count()) {
                throw ValidationException::withMessages(['productos' => 'Uno de los productos ya no está disponible.']);
            }

            /* 4) Totales (misma fórmula que el navegador) */
            $lineas = [];
            $bruto  = 0;
            foreach ($d['productos'] as $item) {
                $cant  = round((float) $item['cantidad'], 2);
                $costo = round((float) $item['costo'], 2);
                $gross = round($cant * $costo, 2);
                $bruto += $gross;

                if ($productos[$item['id']]->maneja_lotes && blank($item['lote'] ?? null)) {
                    throw ValidationException::withMessages(['productos' => "«{$productos[$item['id']]->nombre}» controla lotes: indica el lote y su vencimiento."]);
                }

                $lineas[] = [
                    'p'     => $productos[$item['id']],
                    'cant'  => $cant,
                    'costo' => $costo,
                    'gross' => $gross,
                    'lote'  => filled($item['lote'] ?? null) ? trim($item['lote']) : null,
                    'vence' => filled($item['vence'] ?? null) ? $item['vence'] : null,
                    // Nuevo precio de venta indicado al registrar (null = no cambia)
                    'precio' => filled($item['precio_venta'] ?? null) ? round((float) $item['precio_venta'], 2) : null,
                ];
            }
            $bruto = round($bruto, 2);

            if (! $afecto) {
                $base = $bruto;
                $igv = 0.0;
                $total = $bruto;
            } elseif ($incluyeIgv) {
                $total = $bruto;
                $base  = round($total / (1 + $igvPct / 100), 2);
                $igv   = round($total - $base, 2);
            } else {
                $base  = $bruto;
                $igv   = round($base * $igvPct / 100, 2);
                $total = round($base + $igv, 2);
            }

            foreach ($lineas as &$l) {
                if (! $afecto) {
                    $l['base'] = $l['gross'];
                    $l['igv']  = 0.0;
                } elseif ($incluyeIgv) {
                    $l['base'] = round($l['gross'] / (1 + $igvPct / 100), 2);
                    $l['igv']  = round($l['gross'] - $l['base'], 2);
                } else {
                    $l['base'] = $l['gross'];
                    $l['igv']  = round($l['gross'] * $igvPct / 100, 2);
                }
                // Costo unitario SIN IGV: es el que va al kardex y al producto
                $l['costo_neto'] = $l['cant'] > 0 ? round($l['base'] / $l['cant'], 2) : 0.0;
            }
            unset($l);

            // El residuo de redondeo va a la última línea: Σ líneas = cabecera
            $ult = array_key_last($lineas);
            $lineas[$ult]['base'] = round($lineas[$ult]['base'] + ($base - array_sum(array_column($lineas, 'base'))), 2);
            $lineas[$ult]['igv']  = round($lineas[$ult]['igv'] + ($igv - array_sum(array_column($lineas, 'igv'))), 2);

            /* 5) Cabecera */
            $compra = Compra::create([
                'empresa_id'       => $empresaId,
                'almacen_id'       => $d['almacen_id'],
                'proveedor_id'     => $proveedor->id,
                'user_id'          => $userId,
                'serie'            => $d['serie'] ?? null,
                'numero'           => $d['numero'] ?? null,
                'fecha_emision'    => $fecha,
                'tipo_comprobante' => $d['tipo_comprobante'],
                'condicion_pago'   => $d['condicion_pago'] ?? 'contado',
                // Crédito sin fecha: vence a los 30 días de la emisión
                'fecha_vencimiento' => ($d['condicion_pago'] ?? 'contado') === 'credito'
                    ? ($d['fecha_vencimiento'] ?? $fecha->copy()->addDays(30)->toDateString())
                    : null,
                'moneda'           => $d['moneda'] ?? 'PEN',
                'subtotal'         => $base,
                'total_impuestos'  => $igv,
                'total'            => $total,
                'estado'           => Compra::REGISTRADA,
                'observacion'      => $d['observacion'] ?? null,
            ]);

            /* 6) Detalle + lote + stock + kardex + costo */
            $ref = ['tipo' => 'compra', 'id' => $compra->id, 'serie' => $compra->serie, 'numero' => $compra->numero];

            foreach ($lineas as $l) {
                /** @var Producto $p */
                $p    = $l['p'];
                $lote = (! $p->es_servicio && $l['lote']) ? $this->lotes->obtenerOCrear($empresaId, $p, $l['lote'], $l['vence']) : null;

                $compra->detalles()->create([
                    'producto_id'     => $p->id,
                    'lote_id'         => $lote?->id,
                    'tipo_unidad_id'  => $p->tipo_unidad_id,
                    'descripcion'     => Str::limit($p->nombre, 255, ''),
                    'cantidad'        => $l['cant'],
                    'precio_unitario' => $l['costo'],
                    'subtotal'        => $l['base'],
                    'impuesto'        => $l['igv'],
                    'total'           => round($l['base'] + $l['igv'], 2),
                ]);

                // Precio de venta actualizado en el mismo registro
                if ($l['precio'] !== null && abs($l['precio'] - (float) $p->precio_unitario) >= 0.005) {
                    $this->preciosActualizados[] = [$p->nombre, (float) $p->precio_unitario, $l['precio']];
                    $p->update(['precio_unitario' => $l['precio']]);
                }

                if ($p->es_servicio) {
                    continue;   // los servicios no tienen stock ni costo
                }

                $stockAntes = (float) AlmacenProducto::query()->where('producto_id', $p->id)->sum('stock');

                if ($moverStock) {
                    $this->inventario->ingreso($p, (int) $d['almacen_id'], $l['cant'], [
                        'empresa_id' => $empresaId,
                        'user_id'    => $userId,
                        'ref'        => $ref,
                        'costo'      => $l['costo_neto'],
                        'lote'       => $lote,
                        'fecha'      => $fecha,
                        'obs'        => "Compra {$compra->numero_completo} · {$proveedor->nombre}",
                    ]);
                }

                $this->actualizarCosto($p, $stockAntes, $l['cant'], $l['costo_neto'], $costeo);
            }

            if ($this->preciosActualizados) {
                $detalle = collect($this->preciosActualizados)
                    ->map(fn ($c) => "{$c[0]}: S/ " . number_format($c[1], 2) . " → S/ " . number_format($c[2], 2))
                    ->implode('; ');
                $compra->update(['observacion' => trim(($compra->observacion ? $compra->observacion . "
" : '') . "Precios de venta actualizados: {$detalle}.")]);
            }

            /* 7) Pago al contado (en efectivo sale del turno de caja abierto del usuario) */
            if (($d['condicion_pago'] ?? 'credito') === 'contado') {
                $sesion = ($d['metodo_pago'] ?? null) === 'efectivo' ? $this->cajas->sesionAbierta($empresaId, $userId) : null;

                Pago::create([
                    'empresa_id'  => $empresaId,
                    'compra_id'   => $compra->id,
                    'caja_sesion_id' => $sesion?->id,
                    'user_id'     => $userId,
                    'tipo'        => Pago::TIPO_PAGO,
                    'metodo_pago' => $d['metodo_pago'],
                    'monto'       => $total,
                    'moneda'      => $compra->moneda,
                    'referencia'  => $d['referencia_pago'] ?? null,
                    'fecha_pago'  => $fecha,
                    'estado'      => Pago::REGISTRADO,
                ]);
            }

            // Recepción de una orden de compra: suma lo recibido a la orden
            if (filled($d['orden_compra_id'] ?? null)) {
                app(OrdenCompraService::class)->registrarRecepcion((int) $d['orden_compra_id'], $compra->load('detalles'));
            }

            return $compra;
        });
    }

    /** Registra un pago (total o parcial) de una compra a crédito */
    public function registrarPago(Compra $compra, array $d, int $userId): Pago
    {
        return app(CuentasService::class)->registrar($compra, $d, User::findOrFail($userId));
    }

    /**
     * Anula una compra: saca del almacén lo que ingresó (con su movimiento en kardex)
     * y anula sus pagos. Si parte de esa mercadería ya se vendió, no se puede anular.
     * El costo del producto no se revierte.
     */
    public function anular(Compra $compra, int $userId): void
    {
        DB::transaction(function () use ($compra, $userId) {
            $c = Compra::query()->lockForUpdate()->with(['detalles.producto', 'detalles.lote', 'empresa'])->findOrFail($compra->id);

            if (! $c->esAnulable()) {
                throw ValidationException::withMessages(['compra' => 'Solo se pueden anular compras registradas.']);
            }

            if ($c->empresa->controlar_stock) {
                $ref = ['tipo' => 'compra', 'id' => $c->id, 'serie' => $c->serie, 'numero' => $c->numero];

                foreach ($c->detalles as $detalle) {
                    $p = $detalle->producto;
                    if (! $p || $p->es_servicio) {
                        continue;
                    }

                    try {
                        $this->inventario->egreso($p, $c->almacen_id, $detalle->cantidad, [
                            'empresa_id' => $c->empresa_id,
                            'user_id'    => $userId,
                            'ref'        => $ref,
                            'costo'      => $detalle->cantidad > 0 ? round($detalle->subtotal / $detalle->cantidad, 2) : null,
                            'lote'       => $detalle->lote,
                            'fecha'      => now(),
                            'obs'        => "Anulación de compra {$c->numero_completo}",
                        ]);
                    } catch (ValidationException) {
                        throw ValidationException::withMessages([
                            'compra' => "No se puede anular: ya no hay stock suficiente de «{$p->nombre}» para devolver al proveedor (parte se vendió o se movió).",
                        ]);
                    }
                }
            }

            $c->pagos()->where('estado', Pago::REGISTRADO)->update(['estado' => Pago::ANULADO]);
            app(OrdenCompraService::class)->revertirRecepcion($c->load('detalles'));
            $c->update(['estado' => Compra::ANULADA]);
        });
    }

    private function actualizarCosto(Producto $p, float $stockAntes, float $cantidad, float $costo, string $costeo): void
    {
        $nuevo = $costo;

        if ($costeo === 'promedio' && $stockAntes > 0) {
            $nuevo = round(($stockAntes * (float) $p->costo_unitario + $cantidad * $costo) / ($stockAntes + $cantidad), 2);
        }

        $p->update(['costo_unitario' => $nuevo]);
    }
}
