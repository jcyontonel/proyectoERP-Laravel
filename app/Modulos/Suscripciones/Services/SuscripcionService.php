<?php

namespace App\Modulos\Suscripciones\Services;

use App\Modulos\Erp\Models\Almacen;
use App\Modulos\Nucleo\Models\Empresa;
use App\Modulos\Suscripciones\Models\Oferta;
use App\Modulos\Suscripciones\Models\PagoSuscripcion;
use App\Modulos\Suscripciones\Models\Plan;
use App\Modulos\Suscripciones\Models\ProductoPlataforma;
use App\Modulos\Suscripciones\Models\Titular;
use App\Modulos\Catalogo\Models\Producto;
use App\Modulos\Suscripciones\Models\Suscripcion;
use App\Modulos\Erp\Models\Venta;
use App\Modulos\Suscripciones\Services\ComprobanteSuscripcionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Suscripciones: cada empresa tiene a lo más una vigente por producto (ERP, Página web); una suscripción
 * la paga un titular y puede cubrir varias empresas (plan.max_empresas). Límites del plan por empresa,
 * vigencia y cobros.
 *  - vence_el es el último día pagado. Registrar un pago lo extiende 1, 3 o 12 meses.
 *  - El estado efectivo se calcula por fechas (Suscripcion::estadoEfectivo): no depende de una tarea programada.
 *  - Sin suscripción: sin límites si config('suscripciones.sin_plan_ilimitado') (empresas anteriores a los planes).
 */
class SuscripcionService
{
    /** Memoria por petición (el servicio es scoped) */
    private array $memo = [];

    /* ═════════════════════════════ Consultas ═════════════════════════════ */

    /** La suscripción vigente (no cancelada) que cubre a la empresa en ese producto */
    public function vigente(int $empresaId, string $producto = ProductoPlataforma::ERP): ?Suscripcion
    {
        $clave = "s.$empresaId.$producto";
        if (! array_key_exists($clave, $this->memo)) {
            $this->memo[$clave] = Suscripcion::query()->deEmpresa($empresaId)->delProducto($producto)->vigentes()
                ->with('plan.producto')->latest('id')->first();
        }

        return $this->memo[$clave];
    }

    /** Consumo actual: comprobantes del mes, productos, usuarios y almacenes */
    public function uso(int $empresaId): array
    {
        return $this->memo["u.$empresaId"] ??= [
            'comprobantes' => $this->comprobantesDelMes($empresaId),
            'productos'    => Producto::query()->where('empresa_id', $empresaId)->count(),
            'usuarios'     => DB::table('empresa_user')->join('users', 'users.id', '=', 'empresa_user.user_id')
                ->where('empresa_user.empresa_id', $empresaId)->where('users.activo', true)->whereNull('users.deleted_at')->count(),
            'almacenes'    => Almacen::query()->where('empresa_id', $empresaId)->count(),
        ];
    }

    /**
     * Resumen para el ERP y el proyecto admin: plan, estado, vencimiento y consumo de cada límite.
     *
     * @return array{plan: ?Plan, suscripcion: ?Suscripcion, estado: string, dias: ?int, limites: array}
     */
    /**
     * Módulos que incluye el plan de la empresa en el producto (null = todos). El plan manda aunque la suscripción
     * esté vencida o suspendida (en ese caso además rige el modo solo lectura). Sin suscripción al ERP: todos, si
     * sin_plan_ilimitado (empresas anteriores a los planes); los demás productos siempre exigen su suscripción.
     *
     * @return array<int, string>|null
     */
    public function modulos(int $empresaId, string $producto = ProductoPlataforma::ERP): ?array
    {
        $s = $this->vigente($empresaId, $producto);
        if (! $s) {
            return $producto === ProductoPlataforma::ERP && config('suscripciones.sin_plan_ilimitado') ? null : [];
        }

        return $s->plan?->modulos;
    }

    public function resumen(int $empresaId): array
    {
        $s = $this->vigente($empresaId);
        $uso = $this->uso($empresaId);
        $limites = [];
        foreach (Plan::LIMITES as $recurso => [, $etiqueta]) {
            $max = $s?->plan?->limite($recurso);
            $limites[$recurso] = [
                'etiqueta' => $etiqueta,
                'usado'    => $uso[$recurso],
                'maximo'   => $max,
                'fraccion' => $max ? min($uso[$recurso] / $max, 1) : 0.0,
            ];
        }

        return [
            'plan'        => $s?->plan,
            'suscripcion' => $s,
            'estado'      => $s ? $s->estadoEfectivo() : 'sin_plan',
            'dias'        => $s?->diasRestantes(),
            'limites'     => $limites,
        ];
    }

    /**
     * Exige que la empresa pueda operar y que no supere el límite del recurso (ValidationException si no).
     * $recurso: comprobantes | productos | usuarios | almacenes
     */
    public function exigir(int $empresaId, string $recurso, int $cantidad = 1): void
    {
        $s = $this->vigente($empresaId);
        if (! $s) {
            if (config('suscripciones.sin_plan_ilimitado')) {
                return;
            }
            throw ValidationException::withMessages(['suscripcion' => 'La empresa no tiene un plan activo. Comunícate con soporte para activarlo.']);
        }

        if (! $s->permiteOperar()) {
            throw ValidationException::withMessages(['suscripcion' => $s->estadoEfectivo() === Suscripcion::CANCELADA
                ? 'La suscripción de la empresa fue cancelada. Puedes consultar tu información, pero no registrar nuevos documentos.'
                : 'La suscripción está suspendida' . ($s->motivo_suspension ? " ({$s->motivo_suspension})" : ' por falta de pago')
                    . '. Puedes consultar tu información; para volver a emitir, regulariza el pago.']);
        }

        $max = $s->plan->limite($recurso);
        if ($max !== null && $this->uso($empresaId)[$recurso] + $cantidad > $max) {
            $etiqueta = Plan::LIMITES[$recurso][1];
            throw ValidationException::withMessages(['suscripcion' => "Llegaste al límite de tu plan {$s->plan->nombre}: {$max} {$etiqueta}. "
                . 'Para seguir, cambia a un plan superior.']);
        }
    }

    /* ═════════════════════════════ Operaciones (proyecto admin) ═════════════════════════════ */

    /**
     * Asigna un plan a la empresa (alta o cambio): cancela su suscripción vigente del mismo producto y crea la
     * nueva desde hoy, a nombre del titular (titular_id, o la propia empresa como titular).
     *  - prueba: usa los días de prueba del plan, sin cobro.
     *  - pagado: registra el primer pago (activa hasta inicio + meses).
     *  - si no: queda un cobro pendiente y la empresa tiene los días de gracia para pagarlo.
     *
     * @param  array{periodicidad: string, titular_id?: ?int, prueba?: bool, pagado?: bool, oferta?: ?string, metodo?: ?string, referencia?: ?string, renovacion_automatica?: bool, notas?: ?string}  $d
     */
    public function asignar(Empresa $empresa, Plan $plan, array $d, ?string $registradoPor = null): Suscripcion
    {
        return DB::transaction(function () use ($empresa, $plan, $d, $registradoPor) {
            $periodicidad = $d['periodicidad'];
            $precio = $plan->precios()->where('periodicidad', $periodicidad)->where('activo', true)->first()
                ?? throw ValidationException::withMessages(['periodicidad' => "El plan {$plan->nombre} no tiene precio {$periodicidad} activo."]);
            $oferta = filled($d['oferta'] ?? null) ? $this->oferta($d['oferta'], $plan, $periodicidad) : null;

            $producto = $plan->producto->codigo;
            $titular = filled($d['titular_id'] ?? null) ? Titular::findOrFail($d['titular_id']) : Titular::deEmpresa($empresa);

            // La anterior del mismo producto queda cancelada y su cobro pendiente ya no corresponde
            Suscripcion::query()->deEmpresa($empresa->id)->delProducto($producto)->vigentes()->lockForUpdate()->get()
                ->each(function (Suscripcion $s) use ($plan) {
                    $s->pagos()->where('estado', PagoSuscripcion::PENDIENTE)->update(['estado' => PagoSuscripcion::ANULADO, 'notas' => "Reemplazado por el cambio al plan {$plan->nombre}"]);
                    $s->update(['estado' => Suscripcion::CANCELADA, 'cancelada_el' => now()]);
                });

            $prueba = ($d['prueba'] ?? false) && $plan->dias_prueba > 0;
            $hoy = today();

            $s = Suscripcion::create([
                'empresa_id'            => $empresa->id,
                'titular_id'            => $titular->id,
                'plan_id'               => $plan->id,
                'periodicidad'          => $periodicidad,
                'precio'                => $precio->precio,
                'oferta_id'             => $oferta?->id,
                'estado'                => $prueba ? Suscripcion::PRUEBA : Suscripcion::ACTIVA,
                'inicio'                => $hoy,
                // Prueba: hasta el fin de la prueba. Sin pagar: vence ayer (corre la gracia para pagar el primer periodo)
                'vence_el'              => $prueba ? $hoy->copy()->addDays($plan->dias_prueba - 1) : $hoy->copy()->subDay(),
                'renovacion_automatica' => $d['renovacion_automatica'] ?? true,
                'notas'                 => $d['notas'] ?? null,
            ]);

            $s->empresas()->attach($empresa->id);

            if ($oferta) {
                $oferta->increment('usos');
            }

            if (! $prueba) {
                $cobro = $this->generarCobro($s, primerPago: true);
                if (($d['pagado'] ?? false) || $cobro->monto <= 0) {
                    $this->registrarPago($s, ['pago_id' => $cobro->id, 'metodo' => $d['metodo'] ?? null, 'referencia' => $d['referencia'] ?? null, 'comprobante' => $d['comprobante'] ?? null], $registradoPor);
                }
            }

            $this->olvidar($empresa->id);

            return $s->refresh();
        });
    }

    /**
     * Cobro pendiente del siguiente periodo (si ya hay uno pendiente, lo devuelve).
     * El monto es el precio actual del plan en esa periodicidad, con la oferta si corresponde.
     */
    public function generarCobro(Suscripcion $s, bool $primerPago = false): PagoSuscripcion
    {
        $pendiente = $s->pagos()->where('estado', PagoSuscripcion::PENDIENTE)->first();
        if ($pendiente) {
            return $pendiente;
        }

        $bruto = (float) ($s->plan->precios()->where('periodicidad', $s->periodicidad)->value('precio') ?? $s->precio);
        $oferta = $s->oferta && ($primerPago || ! $s->oferta->solo_primer_pago) ? $s->oferta : null;
        $descuento = $oferta?->descuento($bruto) ?? 0.0;
        [$desde, $hasta] = $this->periodoSiguiente($s);

        return $s->pagos()->create([
            'empresa_id'    => $s->empresa_id,
            'oferta_id'     => $oferta?->id,
            'periodo_desde' => $desde,
            'periodo_hasta' => $hasta,
            'meses'         => $s->meses(),
            'monto_bruto'   => $bruto,
            'descuento'     => $descuento,
            'monto'         => round($bruto - $descuento, 2),
            'estado'        => PagoSuscripcion::PENDIENTE,
        ]);
    }

    /**
     * Registra el pago de un periodo: usa el cobro pendiente (o lo genera), lo marca pagado y extiende
     * la suscripción. Si estaba suspendida, vuelve a activarse.
     *
     * @param  array{pago_id?: ?int, metodo?: ?string, referencia?: ?string, fecha_pago?: ?string, notas?: ?string}  $d
     */
    public function registrarPago(Suscripcion $s, array $d, ?string $registradoPor = null): PagoSuscripcion
    {
        return DB::transaction(function () use ($s, $d, $registradoPor) {
            $s = Suscripcion::query()->lockForUpdate()->with('plan', 'oferta')->findOrFail($s->id);
            if ($s->estado === Suscripcion::CANCELADA) {
                throw ValidationException::withMessages(['suscripcion' => 'La suscripción está cancelada: asigna un plan nuevo.']);
            }

            $pago = filled($d['pago_id'] ?? null)
                ? $s->pagos()->where('estado', PagoSuscripcion::PENDIENTE)->findOrFail($d['pago_id'])
                : $this->generarCobro($s);

            // El periodo se calcula al pagar: si estuvo suspendida mucho tiempo, empieza hoy
            [$desde, $hasta] = $this->periodoSiguiente($s);

            $pago->update([
                'periodo_desde'  => $desde,
                'periodo_hasta'  => $hasta,
                'estado'         => PagoSuscripcion::PAGADO,
                'metodo'         => $d['metodo'] ?? 'transferencia',
                'referencia'     => $d['referencia'] ?? null,
                'fecha_pago'     => isset($d['fecha_pago']) ? Carbon::parse($d['fecha_pago'])->setTimeFrom(now()) : now(),
                'registrado_por' => $registradoPor,
                'notas'          => $d['notas'] ?? $pago->notas,
            ]);

            $s->update(['vence_el' => $hasta, 'estado' => Suscripcion::ACTIVA, 'motivo_suspension' => null]);
            $this->olvidar($s->empresa_id);

            // Factura o boleta electrónica del pago (después del commit: si SUNAT o el facturador fallan, el pago queda igual)
            DB::afterCommit(fn () => app(ComprobanteSuscripcionService::class)->emitirSeguro($pago->refresh(), $d['comprobante'] ?? null));

            return $pago->refresh();
        });
    }

    /** Anula un cobro. Si estaba pagado, la vigencia retrocede (solo el último periodo pagado) */
    public function anularPago(PagoSuscripcion $pago, ?string $motivo = null): void
    {
        DB::transaction(function () use ($pago, $motivo) {
            $p = PagoSuscripcion::query()->lockForUpdate()->findOrFail($pago->id);
            if ($p->estado === PagoSuscripcion::ANULADO) {
                throw ValidationException::withMessages(['pago' => 'El cobro ya estaba anulado.']);
            }

            if ($p->estado === PagoSuscripcion::PAGADO) {
                $s = Suscripcion::query()->lockForUpdate()->findOrFail($p->suscripcion_id);
                if (! $s->vence_el->isSameDay($p->periodo_hasta)) {
                    throw ValidationException::withMessages(['pago' => 'Solo se puede anular el último periodo pagado: anula primero los pagos posteriores.']);
                }
                $s->update(['vence_el' => $p->periodo_desde->copy()->subDay()]);
            }

            $p->update(['estado' => PagoSuscripcion::ANULADO, 'notas' => trim(($p->notas ? $p->notas . "\n" : '') . 'Anulado' . ($motivo ? ": {$motivo}" : '') . ' el ' . now()->format('d/m/Y H:i'))]);
            $this->olvidar($p->empresa_id);

            // Su comprobante: baja en SUNAT (hasta 7 días) o nota de crédito
            DB::afterCommit(function () use ($p) {
                try {
                    app(ComprobanteSuscripcionService::class)->anular($p->refresh());
                } catch (\Throwable $e) {
                    report($e);
                }
            });
        });
    }

    /** Suma una empresa a la suscripción (hasta plan.max_empresas). No puede tener otra vigente del mismo producto */
    public function agregarEmpresa(Suscripcion $s, Empresa $empresa): void
    {
        DB::transaction(function () use ($s, $empresa) {
            $s = Suscripcion::query()->lockForUpdate()->with('plan.producto')->findOrFail($s->id);
            if ($s->estado === Suscripcion::CANCELADA) {
                throw ValidationException::withMessages(['suscripcion' => 'La suscripción está cancelada.']);
            }
            if ($s->empresas()->whereKey($empresa->id)->exists()) {
                return;
            }
            $max = $s->plan->max_empresas;
            if ($max !== null && $s->empresas()->count() >= $max) {
                throw ValidationException::withMessages(['empresa' => "El plan {$s->plan->nombre} cubre hasta {$max} " . ($max === 1 ? 'empresa' : 'empresas') . '. Para sumar otra, cambia a un plan superior.']);
            }
            $otra = $this->vigente($empresa->id, $s->plan->producto->codigo);
            if ($otra) {
                throw ValidationException::withMessages(['empresa' => "{$empresa->razon_social} ya tiene su propia suscripción a {$s->plan->producto->nombre} (plan {$otra->plan->nombre}): cancélala primero."]);
            }
            $s->empresas()->attach($empresa->id);
        });
        $this->olvidar($empresa->id);
    }

    /** Quita una empresa (la principal no: para eso se cancela o se cambia de plan) */
    public function quitarEmpresa(Suscripcion $s, Empresa $empresa): void
    {
        if ($s->empresa_id === $empresa->id) {
            throw ValidationException::withMessages(['empresa' => 'La empresa principal no se quita: cancela la suscripción o cambia de plan.']);
        }
        $s->empresas()->detach($empresa->id);
        $this->olvidar($empresa->id);
    }

    public function suspender(Suscripcion $s, string $motivo): void
    {
        $s->update(['estado' => Suscripcion::SUSPENDIDA, 'motivo_suspension' => $motivo]);
        $this->olvidar($s->empresa_id);
    }

    /** Quita la suspensión manual (si además está vencida, el estado efectivo lo indicará) */
    public function reactivar(Suscripcion $s): void
    {
        $s->update(['estado' => Suscripcion::ACTIVA, 'motivo_suspension' => null]);
        $this->olvidar($s->empresa_id);
    }

    public function cancelar(Suscripcion $s, ?string $motivo = null): void
    {
        DB::transaction(function () use ($s, $motivo) {
            $s->pagos()->where('estado', PagoSuscripcion::PENDIENTE)->update(['estado' => PagoSuscripcion::ANULADO]);
            $s->update(['estado' => Suscripcion::CANCELADA, 'cancelada_el' => now(), 'notas' => trim(($s->notas ? $s->notas . "\n" : '') . 'Cancelada' . ($motivo ? ": {$motivo}" : ''))]);
        });
        $this->olvidar($s->empresa_id);
    }

    /**
     * Tarea diaria: genera el cobro pendiente de las suscripciones que vencen dentro de los días de aviso.
     *
     * @return int cobros generados
     */
    public function procesar(): int
    {
        $limite = today()->addDays((int) config('suscripciones.dias_aviso'))->toDateString();
        $n = 0;

        Suscripcion::query()->vigentes()->where('renovacion_automatica', true)
            ->where('estado', '!=', Suscripcion::SUSPENDIDA)
            ->whereDate('vence_el', '<=', $limite)
            ->whereDoesntHave('pagos', fn ($q) => $q->where('estado', PagoSuscripcion::PENDIENTE))
            ->with('plan', 'oferta')
            ->each(function (Suscripcion $s) use (&$n) {
                $this->generarCobro($s);
                $n++;
            });

        return $n;
    }

    /* ═════════════════════════════ Auxiliares ═════════════════════════════ */

    /** [desde, hasta] del próximo periodo: continúa tras vence_el, o desde hoy si ya estaba suspendida por fecha */
    private function periodoSiguiente(Suscripcion $s): array
    {
        $desde = $s->estadoEfectivo() === Suscripcion::SUSPENDIDA || $s->vence_el->lt($s->inicio)
            ? today()->max($s->inicio)
            : $s->vence_el->copy()->addDay();

        return [$desde, $desde->copy()->addMonthsNoOverflow($s->meses())->subDay()];
    }

    /** Oferta por código o id, vigente y aplicable al plan y periodicidad */
    private function oferta(string|int $clave, Plan $plan, string $periodicidad): Oferta
    {
        $oferta = Oferta::query()->vigentes()
            ->where(fn ($q) => is_numeric($clave) ? $q->whereKey($clave) : $q->where('codigo', strtoupper((string) $clave)))
            ->first();

        if (! $oferta || ! $oferta->aplicaA($plan->id, $periodicidad)) {
            throw ValidationException::withMessages(['oferta' => 'La oferta no existe, ya no está vigente o no aplica a este plan y periodicidad.']);
        }

        return $oferta;
    }

    /** Facturas, boletas, tickets y notas emitidos en el mes (los anulados también consumen número) */
    private function comprobantesDelMes(int $empresaId): int
    {
        $rango = [now()->startOfMonth(), now()->endOfMonth()];

        return Venta::query()->where('empresa_id', $empresaId)->where('estado', '!=', Venta::BORRADOR)
            ->whereBetween('fecha_emision', $rango)->count()
            + DB::table('notas_credito_debito')->where('empresa_id', $empresaId)->whereNull('deleted_at')
                ->where('estado', '!=', 'borrador')->whereBetween('fecha_emision', $rango)->count();
    }

    private function olvidar(int $empresaId): void
    {
        foreach (array_keys($this->memo) as $clave) {
            if (str_starts_with($clave, "s.$empresaId.") || $clave === "u.$empresaId") {
                unset($this->memo[$clave]);
            }
        }
    }
}
