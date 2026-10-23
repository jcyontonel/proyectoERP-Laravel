<?php

namespace App\Modulos\Plataforma\Services;

use App\Modulos\Facturacion\Services\FacturacionElectronicaService;

use App\Modulos\Facturacion\Models\Documento;
use App\Modulos\Erp\Models\Correlativo;
use App\Modulos\Nucleo\Models\Empresa;
use App\Modulos\Plataforma\Models\PagoSuscripcion;
use App\Modulos\Plataforma\Models\Titular;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Comprobante electrónico de cada pago de suscripción al ERP.
 *  - Emisor: la empresa dueña de la plataforma (config suscripciones.facturacion.emisor_empresa_id),
 *    con sus series F900 / B900 (módulo "suscripcion") y su configuración SUNAT.
 *  - Cliente: el titular de la suscripción (factura a su RUC, o boleta si se pide o si es DNI).
 *  - Anular el pago: baja si el comprobante tiene hasta 7 días; si no, nota de crédito (FC01 / BC01).
 * El envío y los reintentos los hace FacturacionElectronicaService (documento en la tabla documentos).
 */
class ComprobanteSuscripcionService
{
    public const FACTURA = 'factura';
    public const BOLETA = 'boleta';
    public const NINGUNO = 'ninguno';

    public function __construct(private FacturacionElectronicaService $facturacion)
    {
    }

    /** Empresa de la plataforma (Caral Code): por RUC (SUSCRIPCIONES_EMISOR_RUC) o por id */
    public function emisor(): ?Empresa
    {
        $ruc = config('suscripciones.facturacion.emisor_ruc');
        $id = config('suscripciones.facturacion.emisor_empresa_id');

        return match (true) {
            filled($ruc) => Empresa::where('ruc', $ruc)->first(),
            filled($id)  => Empresa::find($id),
            default      => null,
        };
    }

    public function activo(): bool
    {
        return $this->facturacion->activo() && $this->emisor() !== null;
    }

    /** Emite (o reintenta) el comprobante de un pago registrado. $tipo: factura | boleta | ninguno */
    public function emitir(PagoSuscripcion $pago, ?string $tipo = null): ?Documento
    {
        if ($tipo === self::NINGUNO || ! $this->activo() || ! $pago->esPagado() || $pago->monto <= 0) {
            return null;
        }

        $reg = $pago->comprobante()->first();
        if (! $reg) {
            $titular = $pago->suscripcion?->titular;
            $codigo = $tipo === self::BOLETA || ($titular && ! $titular->esRuc()) ? '03' : '01';   // sin RUC no hay factura
            $reg = DB::transaction(function () use ($pago, $codigo) {
                [$serie, $numero] = $this->numerar('suscripcion', $codigo, fn (Correlativo $c) => true);

                return Documento::create([
                    'empresa_id' => $this->emisor()->id, 'comprobante_type' => $pago->getMorphClass(), 'comprobante_id' => $pago->id,
                    'tipo' => $codigo, 'serie' => $serie, 'numero' => $numero, 'estado' => Documento::PENDIENTE, 'fecha_emision' => now(),
                ]);
            });
        }

        return $this->reintentar($reg);
    }

    /** Nunca interrumpe el registro del pago */
    public function emitirSeguro(PagoSuscripcion $pago, ?string $tipo = null): ?Documento
    {
        try {
            return $this->emitir($pago, $tipo);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    public function reintentar(Documento $reg): Documento
    {
        $pago = $reg->comprobante;

        return $this->facturacion->enviarRegistro($reg, fn () => $reg->tipo === '07' ? $this->datosNota($pago, $reg) : $this->datos($pago, $reg));
    }

    /** El pago se anuló: baja del comprobante (hasta 7 días) o nota de crédito (después) */
    public function anular(PagoSuscripcion $pago): ?Documento
    {
        $reg = $pago->comprobante()->first();
        if (! $reg || ! $this->facturacion->activo()) {
            return null;
        }
        if ($nota = $pago->notaCredito()->first()) {
            return $nota;
        }

        if (! $reg->fueAceptado()) {
            if (! $reg->enviado_at && $reg->estado !== Documento::ANULADO) {
                // Nunca se envió a SUNAT: ya no se envía (el número queda sin usar)
                $reg->update(['estado' => Documento::ANULADO, 'ultimo_error' => 'No se envió a SUNAT: el pago se anuló antes.', 'proximo_intento_at' => null]);
            }

            return $reg;   // si ya está en SUNAT, al aceptarse se pedirá la baja (FacturacionElectronicaService::alCambiarEstado)
        }

        $dias = $reg->created_at->copy()->startOfDay()->diffInDays(today());
        if ($dias <= (int) config('suscripciones.facturacion.dias_para_baja')) {
            return $this->facturacion->anularRegistro($reg);
        }

        // Fuera de plazo: nota de crédito por anulación de la operación (serie FC… o BC… según el comprobante)
        $letra = $reg->serie[0];
        $nota = DB::transaction(function () use ($pago, $letra) {
            [$serie, $numero] = $this->numerar('nota', '07', fn (Correlativo $c) => str_starts_with($c->serie, $letra), $letra . 'C01');

            return Documento::create([
                'empresa_id' => $this->emisor()->id, 'comprobante_type' => $pago->getMorphClass(), 'comprobante_id' => $pago->id,
                'tipo' => '07', 'serie' => $serie, 'numero' => $numero, 'estado' => Documento::PENDIENTE, 'fecha_emision' => now(),
            ]);
        });

        return $this->reintentar($nota);
    }

    /* ───────────────────────── Privado ───────────────────────── */

    /** Toma el siguiente número de la serie activa del emisor (la crea si no existe) */
    private function numerar(string $modulo, string $tipo, callable $filtro, ?string $serieDefecto = null): array
    {
        $emisorId = $this->emisor()->id;
        $corr = Correlativo::query()->deEmpresa($emisorId)->where('modulo', $modulo)->where('tipo', $tipo)->activo()
            ->orderBy('id')->lockForUpdate()->get()->first($filtro);

        if (! $corr) {
            [$descripcion, , $serie] = Correlativo::TIPOS[$modulo][$tipo];
            $corr = Correlativo::firstOrCreate(
                ['empresa_id' => $emisorId, 'tipo' => $tipo, 'serie' => $serieDefecto ?? $serie],
                ['modulo' => $modulo, 'destipo' => $descripcion, 'numero' => 0, 'activo' => true],
            );
            $corr = Correlativo::query()->lockForUpdate()->findOrFail($corr->id);
        }
        $corr->emitir();

        return [$corr->serie, (int) $corr->numero];
    }

    private function datos(PagoSuscripcion $pago, Documento $reg): array
    {
        $pago->loadMissing('empresa', 'suscripcion.plan.producto', 'suscripcion.titular', 'oferta');
        $emisor = Empresa::findOrFail($reg->empresa_id);
        $periodo = config('suscripciones.periodicidades.' . $pago->suscripcion?->periodicidad . '.0', '');

        return [
            'tipo'                 => $reg->tipo,
            'serie'                => $reg->serie,
            'numero'               => (int) $reg->numero,
            'fecha_emision'        => $reg->created_at->format('Y-m-d H:i:s'),   // fija: la de la primera emisión
            'moneda'               => 'PEN',
            'igv_porcentaje'       => (float) ($emisor->igv ?: 18),
            'precios_incluyen_igv' => (bool) config('suscripciones.facturacion.precios_incluyen_igv'),
            'cliente'              => $this->cliente($pago->suscripcion?->titular ?? Titular::deEmpresa($pago->empresa)),
            'items'                => [[
                // Mismo código que el servicio en el catálogo de la empresa emisora (SUSC-BASICO-MENSUAL…)
                'codigo'          => strtoupper('SUSC-' . ($pago->suscripcion?->plan?->codigo ?? $pago->suscripcion?->plan_id) . '-' . $pago->suscripcion?->periodicidad),
                'descripcion'     => mb_substr(trim(config('suscripciones.facturacion.producto') . ' ' . ($pago->suscripcion?->plan?->producto?->nombre ?? '') . ' — Plan ' . ($pago->suscripcion?->plan?->nombre ?? '')
                    . ($periodo ? " ({$periodo})" : '') . ($pago->periodo_desde ? ', del ' . $pago->periodo_desde->format('d/m/Y') . ' al ' . $pago->periodo_hasta->format('d/m/Y') : '')
                    . ($pago->oferta ? ' — oferta ' . $pago->oferta->nombre : '')), 0, 500),
                'unidad'          => 'ZZ',   // servicio
                'cantidad'        => 1,
                'precio_unitario' => (float) $pago->monto,
                'afectacion'      => '10',
            ]],
            'observaciones'        => trim('Pago por ' . (config('suscripciones.metodos_pago.' . $pago->metodo) ?? $pago->metodo) . ($pago->referencia ? " · ref. {$pago->referencia}" : '')),
            'referencia'           => 'suscripcion:' . $pago->id,
        ];
    }

    private function datosNota(PagoSuscripcion $pago, Documento $nota): array
    {
        $original = $pago->comprobante()->firstOrFail();
        $d = $this->datos($pago, $original);

        return array_merge($d, [
            'tipo'          => '07',
            'serie'         => $nota->serie,
            'numero'        => (int) $nota->numero,
            'fecha_emision' => $nota->created_at->format('Y-m-d H:i:s'),
            'afectado'      => ['tipo' => $original->tipo, 'serie' => $original->serie, 'numero' => (int) $original->numero],
            'motivo'        => ['codigo' => '01', 'descripcion' => 'ANULACION DE LA OPERACION'],
            'observaciones' => null,
            'referencia'    => 'suscripcion-nc:' . $pago->id,
        ]);
    }

    /** El comprobante sale a nombre del titular que paga */
    private function cliente(Titular $t): array
    {
        return [
            'tipo_doc'  => $t->tipo_documento,
            'numero'    => $t->numero_documento,
            'nombre'    => $t->nombre,
            'direccion' => $t->direccion,
            'email'     => filter_var($t->correo, FILTER_VALIDATE_EMAIL) ? $t->correo : null,
        ];
    }
}
