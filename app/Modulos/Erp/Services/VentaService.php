<?php

namespace App\Modulos\Erp\Services;

use App\Modulos\Suscripciones\Services\SuscripcionService;

use App\Modulos\Erp\Events\VentaGuardada;
use App\Modulos\Erp\Models\AlmacenProducto;
use App\Modulos\Erp\Models\Cliente;
use App\Modulos\Erp\Models\Correlativo;
use App\Modulos\Nucleo\Models\Empresa;
use App\Modulos\Erp\Models\MovimientoCaja;
use App\Modulos\Erp\Models\Pago;
use App\Modulos\Catalogo\Models\Producto;
use App\Modulos\Nucleo\Models\User;
use App\Modulos\Erp\Models\Venta;
use App\Modulos\Erp\Support\Montos;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VentaService
{
    public function __construct(
        private InventarioService $inventario,
        private CajaService $cajas,
        private FraccionamientoService $fracciones,
    )
    {
    }

    /** Crea una venta nueva (registrada) o un borrador */
    public function crear(array $d, int $userId, int $empresaId): Venta
    {
        return $this->guardar($d, $userId, $empresaId);
    }

    /** Actualiza un borrador (y lo registra si accion = registrar) */
    public function actualizar(Venta $borrador, array $d, int $userId, int $empresaId): Venta
    {
        return $this->guardar($d, $userId, $empresaId, $borrador);
    }

    /**
     * Guarda la venta en una sola transacción:
     *  - bloquea y avanza el correlativo (solo al registrar),
     *  - recalcula todo con los precios de la base de datos,
     *  - guarda cabecera y detalle,
     *  - descuenta stock del almacén y registra el kardex (solo al registrar).
     * IGV, "precios incluyen IGV" y "controlar stock" salen de la tabla empresas.
     */
    private function guardar(array $d, int $userId, int $empresaId, ?Venta $existente = null): Venta
    {
        $empresa    = Empresa::findOrFail($empresaId);
        $borrador   = ($d['accion'] ?? 'registrar') === 'borrador';
        $igvPct     = (float) $empresa->igv;
        $incluyeIgv = (bool) $empresa->precios_incluyen_igv;
        $moverStock = ! $borrador && $empresa->controlar_stock;

        // Plan de suscripción: emitir consume un comprobante del mes (el borrador no)
        if (! $borrador) {
            app(SuscripcionService::class)->exigir($empresaId, 'comprobantes');
        }

        return DB::transaction(function () use ($d, $userId, $empresaId, $existente, $borrador, $igvPct, $incluyeIgv, $moverStock) {
            $ahora = now();

            /* 0) Si se edita un borrador: bloquearlo y verificar */
            if ($existente) {
                $existente = Venta::query()->lockForUpdate()->findOrFail($existente->id);

                if ($existente->empresa_id !== $empresaId || ! $existente->esEditable()) {
                    throw ValidationException::withMessages(['venta' => 'Solo se pueden editar borradores de tu empresa.']);
                }
            }

            /* 0b) Turno de caja: lo que se cobra al registrar (contado o adelanto de un crédito) entra al turno del vendedor */
            $credito  = ($d['condicion_pago'] ?? Venta::CONTADO) === Venta::CREDITO;
            $adelanto = $credito ? round((float) ($d['adelanto'] ?? 0), 2) : 0.0;
            $cobraAhora = ! $borrador && (! $credito || $adelanto > 0);

            $sesion = $borrador ? null : $this->cajas->sesionAbierta($empresaId, $userId);
            if ($cobraAhora && ! $sesion && config('caja.exigir_para_vender')) {
                throw ValidationException::withMessages(['caja' => 'Abre tu turno de caja antes de registrar ventas (puedes guardar la venta como borrador).']);
            }

            /* 1) Correlativo (fila bloqueada hasta el commit para evitar números repetidos) */
            $corr = Correlativo::query()
                ->deEmpresa($empresaId)
                ->where('modulo', Correlativo::MODULO_VENTA)
                ->activo()
                ->lockForUpdate()
                ->find($d['correlativo_id']);

            if (! $corr) {
                throw ValidationException::withMessages(['correlativo_id' => 'El comprobante seleccionado no está disponible.']);
            }

            /* 2) Cliente (las facturas exigen RUC; ventas.cliente_id admite NULL) */
            $clienteId = $d['cliente_id'] ?? null;

            // Al crédito se le vende a alguien identificado: queda en Cuentas por cobrar
            if (! $borrador && $credito && ! $clienteId) {
                throw ValidationException::withMessages(['cliente_id' => 'Para vender al crédito selecciona el cliente.']);
            }

            if (! $borrador && $corr->requiereRuc()) {
                $cliente = $clienteId ? Cliente::with('tipoDocumento')->find($clienteId) : null;

                if (! $cliente?->es_ruc) {
                    throw ValidationException::withMessages(['cliente_id' => 'Para emitir una factura selecciona un cliente con RUC.']);
                }
            }

            /* 3) Productos reales de la BD (nunca se confía en los precios del navegador) */
            $pedido = collect($d['productos'])
                ->groupBy('id')
                ->map(fn ($g) => round($g->sum(fn ($l) => (float) $l['cantidad']), 3));

            $productos = Producto::query()
                ->deEmpresa($empresaId)
                ->activo()
                ->whereIn('id', $pedido->keys())
                ->get()
                ->keyBy('id');

            if ($productos->count() !== $pedido->count()) {
                throw ValidationException::withMessages(['productos' => 'Uno de los productos ya no está disponible.']);
            }

            /* 3b) Producto fraccionado sin stock suficiente (ej. kilos): se parte del origen (ej. se abre un saco) */
            $fraccionados = [];
            if ($moverStock && config('inventario.fraccionar_al_vender')) {
                $usuario = User::findOrFail($userId);
                foreach ($pedido as $id => $cantidad) {
                    $p = $productos[$id];
                    if ($p->es_servicio) {
                        continue;
                    }
                    $disponible = (float) (AlmacenProducto::query()->where('almacen_id', $d['almacen_id'])->where('producto_id', $id)->value('stock') ?? 0);
                    if ($cantidad > $disponible) {
                        array_push($fraccionados, ...$this->fracciones->cubrirFaltante($p, (int) $d['almacen_id'], round($cantidad - $disponible, 4), $usuario));
                    }
                }
            }

            /* 4) Stock disponible (mensaje claro con todos los faltantes; el movimiento real lo vuelve a validar) */
            if ($moverStock) {
                $stocks = AlmacenProducto::query()
                    ->where('almacen_id', $d['almacen_id'])
                    ->whereIn('producto_id', $pedido->keys())
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('producto_id');

                $faltantes = [];
                foreach ($pedido as $id => $cantidad) {
                    $p = $productos[$id];
                    if ($p->es_servicio) {
                        continue;
                    }
                    $disponible = (float) ($stocks[$id]->stock ?? 0);
                    if ($cantidad > $disponible) {
                        $faltantes[] = "Stock insuficiente de «{$p->nombre}»: disponible {$disponible}, solicitado {$cantidad}.";
                    }
                }
                if ($faltantes) {
                    throw ValidationException::withMessages(['productos' => $faltantes]);
                }
            }

            /* 5) Totales (misma fórmula que el navegador: App\Modulos\Erp\Support\Montos) */
            $lineas = [];
            foreach ($pedido as $id => $cantidad) {
                $p = $productos[$id];
                $lineas[] = ['p' => $p, 'cant' => $cantidad, 'precio' => $p->precio_unitario, 'gross' => round($p->precio_unitario * $cantidad, 2)];
            }

            $calculo   = Montos::calcular($lineas, (float) ($d['descuento'] ?? 0), $igvPct, $incluyeIgv);
            $lineas    = $calculo['lineas'];
            $pct       = $calculo['pct'];
            $descTotal = $calculo['descuento'];
            $base      = $calculo['base'];
            $igv       = $calculo['igv'];
            $total     = $calculo['total'];

            /* 6) Serie y número (ventas.numero es BIGINT; índice único empresa+tipo+serie+numero) */
            if ($borrador) {
                // Los borradores NO consumen correlativo: serie BOR y número provisional (se fija con el id)
                $serie  = 'BOR';
                $numero = $existente?->numero ?? random_int(1_000_000_000, 9_000_000_000_000);
            } else {
                $numero = $corr->emitir();   // numero = último emitido → +1 y guarda
                $serie  = $corr->serie;
            }

            /* 7) Cabecera */
            $cabecera = [
                'empresa_id'           => $empresaId,
                'almacen_id'           => $d['almacen_id'],
                'cliente_id'           => $clienteId,
                'user_id'              => $userId,
                'serie'                => $serie,
                'numero'               => $numero,
                'fecha_emision'        => Carbon::parse($d['fecha_emision'])->setTimeFrom($ahora),
                'tipo'                 => $corr->tipo,
                'metodo_pago'          => $d['metodo_pago'],
                'estado'               => $borrador ? Venta::BORRADOR : Venta::COMPLETADA,
                'subtotal'             => $base,
                'total_impuestos'      => $igv,
                'total'                => $total,
                'descuento_porcentaje' => $pct,
                'descuento'            => $descTotal,
                'observaciones'        => $d['observaciones'] ?? null,
                'caja_sesion_id'       => $sesion?->id,
                'condicion_pago'       => $credito ? Venta::CREDITO : Venta::CONTADO,
                'fecha_vencimiento'    => $credito ? ($d['fecha_vencimiento'] ?? Carbon::parse($d['fecha_emision'])->addDays(30)->toDateString()) : null,
            ];

            if ($existente) {
                $existente->update($cabecera);
                $existente->detalles()->forceDelete();   // un borrador no tiene historial que conservar
                $venta = $existente;
            } else {
                $venta = Venta::create($cabecera);

                if ($borrador) {
                    $venta->update(['numero' => $venta->id]);
                }
            }

            /* 8) Detalle */
            $venta->detalles()->createMany(array_map(fn ($l) => [
                'producto_id'     => $l['p']->id,
                'tipo_unidad_id'  => $l['p']->tipo_unidad_id,
                'descripcion'     => Str::limit($l['p']->nombre, 255, ''),
                'cantidad'        => $l['cant'],
                'precio_unitario' => $l['precio'],
                'costo_unitario'  => $l['p']->costo_unitario, // costo al vender: base del reporte de utilidad
                'descuento'       => $l['desc'],
                'subtotal'        => $l['base'],
                'total_impuestos' => $l['igv'],
                'total'           => round($l['base'] + $l['igv'], 2),
            ], $lineas));

            // Los fraccionamientos automáticos quedan enlazados a la venta que los justificó
            foreach ($fraccionados as $f) {
                $f->update(['venta_id' => $venta->id, 'observacion' => "Automático al vender {$venta->numero_completo}"]);
            }

            // Venta que viene de una cotización: la cotización queda convertida
            if (filled($d['cotizacion_id'] ?? null)) {
                app(CotizacionService::class)->marcarConvertida((int) $d['cotizacion_id'], $venta);
            }

            // Otros módulos (p. ej. el pedido web de origen) reaccionan a la venta guardada
            VentaGuardada::dispatch($venta, $d);

            /* 8b) Cobro al contado (o adelanto del crédito): queda en el turno de caja (arqueo y resumen por método de pago) */
            if ($credito && $adelanto >= $venta->total) {
                throw ValidationException::withMessages(['adelanto' => 'El adelanto debe ser menor al total: si paga todo, registra la venta al contado.']);
            }
            $cobro = $credito ? $adelanto : $venta->total;
            if ($cobraAhora && $cobro > 0) {
                Pago::create([
                    'empresa_id'     => $empresaId,
                    'venta_id'       => $venta->id,
                    'caja_sesion_id' => $sesion?->id,
                    'user_id'        => $userId,
                    'tipo'           => Pago::TIPO_COBRO,
                    'metodo_pago'    => $venta->metodo_pago,
                    'monto'          => $cobro,
                    'moneda'         => 'PEN',
                    'fecha_pago'     => $venta->fecha_emision,
                    'observacion'    => $credito ? 'Adelanto de venta al crédito' : null,
                    'estado'         => Pago::REGISTRADO,
                ]);
            }

            /* 9) Salida de stock + kardex */
            if ($moverStock) {
                $ref = [
                    'tipo'   => 'venta',
                    'id'     => $venta->id,
                    'serie'  => $venta->serie,
                    'numero' => Correlativo::formatear((int) $venta->numero),
                ];

                foreach ($lineas as $l) {
                    if ($l['p']->es_servicio) {
                        continue;
                    }
                    $this->inventario->egreso($l['p'], (int) $d['almacen_id'], $l['cant'], [
                        'empresa_id' => $empresaId,
                        'user_id'    => $userId,
                        'ref'        => $ref,
                        'costo'      => $l['p']->costo_unitario,
                        'fecha'      => $venta->fecha_emision,
                        'obs'        => "Venta {$venta->numero_completo}",
                    ]);
                }
            }

            return $venta;
        });
    }

    /**
     * Anula una venta completada y devuelve el stock al almacén (con su movimiento en kardex).
     * El correlativo NO retrocede: el número anulado queda en la serie.
     */
    public function anular(Venta $venta, int $userId): void
    {
        DB::transaction(function () use ($venta, $userId) {
            $v = Venta::query()->lockForUpdate()->with(['detalles.producto', 'empresa'])->findOrFail($venta->id);

            if (! $v->esAnulable()) {
                throw ValidationException::withMessages(['venta' => 'Solo se pueden anular comprobantes completados.']);
            }

            if ($v->empresa->controlar_stock) {
                $ref = [
                    'tipo'   => 'venta',
                    'id'     => $v->id,
                    'serie'  => $v->serie,
                    'numero' => Correlativo::formatear((int) $v->numero),
                ];

                foreach ($v->detalles as $detalle) {
                    $p = $detalle->producto;
                    if (! $p || $p->es_servicio) {
                        continue;
                    }
                    $this->inventario->ingreso($p, $v->almacen_id, $detalle->cantidad, [
                        'empresa_id' => $v->empresa_id,
                        'user_id'    => $userId,
                        'ref'        => $ref,
                        'costo'      => $p->costo_unitario,
                        'fecha'      => now(),
                        'obs'        => "Anulación de venta {$v->numero_completo}",
                    ]);
                }
            }

            $this->revertirCobros($v, $userId);
            app(CotizacionService::class)->liberar($v);
            $v->update(['estado' => Venta::ANULADA]);
        });
    }

    /**
     * Anula los cobros de la venta. Si el turno donde se cobró en efectivo ya cerró, la devolución
     * sale del turno abierto de quien anula (egreso en movimientos_caja).
     */
    private function revertirCobros(Venta $v, int $userId): void
    {
        $cobros = $v->pagos()->where('estado', Pago::REGISTRADO)->with('sesion')->get();

        foreach ($cobros as $cobro) {
            $cobro->update(['estado' => Pago::ANULADO]);

            if ($cobro->metodo_pago !== 'efectivo' || ! $cobro->sesion || $cobro->sesion->estaAbierta()) {
                continue;
            }

            $actual = $this->cajas->sesionAbierta($v->empresa_id, $userId);
            if ($actual) {
                MovimientoCaja::create([
                    'empresa_id'       => $v->empresa_id,
                    'caja_sesion_id'   => $actual->id,
                    'pago_id'          => $cobro->id,
                    'user_id'          => $userId,
                    'tipo'             => MovimientoCaja::EGRESO,
                    'concepto'         => "Devolución por anulación de {$v->numero_completo}",
                    'metodo_pago'      => 'efectivo',
                    'monto'            => $cobro->monto,
                    'fecha_movimiento' => now(),
                    'estado'           => MovimientoCaja::REGISTRADO,
                ]);
            }
        }
    }
}
