<?php

namespace App\Modulos\Facturacion\Services;

use App\Modulos\Erp\Models\Nota;
use App\Modulos\Erp\Models\Venta;
use App\Modulos\Facturacion\Contracts\EmisorComprobantes;
use App\Modulos\Facturacion\Events\EstadoDocumentoCambiado;
use App\Modulos\Facturacion\Models\Documento;
use App\Modulos\Nucleo\Models\Empresa;
use App\Modulos\Suscripciones\Models\PagoSuscripcion;
use App\Modulos\Suscripciones\Services\ComprobanteSuscripcionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Facturas, boletas y notas del ERP: arma los datos desde la venta o la nota y los emite por el
 * contrato EmisorComprobantes (firma en el momento; el envío a SUNAT va en cola).
 * Nunca interrumpe la venta: los errores quedan registrados en el documento.
 */
class FacturacionElectronicaService
{
    public const MOTIVO_ANULACION = 'ANULACION DE LA OPERACION';

    public function __construct(private EmisorComprobantes $emisor)
    {
    }

    public function activo(): bool
    {
        return (bool) config('facturacion.activa');
    }

    /** Factura o boleta registrada (los tickets y borradores no van a SUNAT) */
    public function emitirVenta(Venta $venta): ?Documento
    {
        if (! $this->activo() || ! in_array($venta->tipo, ['01', '03'], true) || ! $venta->esCompletada()) {
            return null;
        }

        return $this->emitir($venta, fn () => $this->datosVenta($venta));
    }

    public function emitirNota(Nota $nota): ?Documento
    {
        if (! $this->activo() || $nota->esAnulada()) {
            return null;
        }

        return $this->emitir($nota, fn () => $this->datosNota($nota));
    }

    /** Emite sin interrumpir nunca la operación del ERP (un fallo inesperado queda en el log) */
    public function emitirSeguro(Venta|Nota $doc): ?Documento
    {
        try {
            return $doc instanceof Nota ? $this->emitirNota($doc) : $this->emitirVenta($doc);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /** Pide la baja en SUNAT (comunicación de baja o resumen diario) del comprobante de una venta, nota o pago */
    public function anular(Model $origen): ?Documento
    {
        if ($origen instanceof PagoSuscripcion) {   // decide entre baja y nota de crédito según el plazo
            return app(ComprobanteSuscripcionService::class)->anular($origen);
        }

        $doc = $this->registro($origen);

        return $doc ? $this->anularRegistro($doc) : null;
    }

    /**
     * Baja de un documento. Si aún no fue aceptado no se puede: se pedirá al aceptarse (alCambiarEstado).
     * Si SUNAT no admite la baja (fuera de plazo…) el motivo queda en ultimo_error.
     */
    public function anularRegistro(Documento $doc): Documento
    {
        if (! $this->activo() || ! $doc->fueAceptado()) {
            return $doc;
        }

        try {
            return $this->emisor->anular($doc, self::MOTIVO_ANULACION);
        } catch (ValidationException $e) {
            $doc->update(['ultimo_error' => 'No se pudo pedir la baja: ' . collect($e->errors())->flatten()->first()]);

            return $doc->refresh();
        }
    }

    /** Reenvío manual: lo pendiente se envía ya; lo rechazado o con error se vuelve a armar con los datos actuales */
    public function reenviar(Documento $doc): Documento
    {
        if ($doc->baja && in_array($doc->baja->estado, [Documento::PENDIENTE, Documento::ERROR], true)) {
            $doc->baja->estado === Documento::ERROR ? $this->anularRegistro($doc) : $this->emisor->enviar($doc->baja);

            return $doc->refresh();
        }
        if ($doc->estado === Documento::PENDIENTE && $doc->datos) {
            $this->emisor->enviar($doc);

            return $doc->refresh();
        }

        return $this->reintentar($doc) ?? $doc->refresh();
    }

    /** Vuelve a armar y emitir un documento según su origen (venta, nota o pago de suscripción) */
    public function reintentar(Documento $doc): ?Documento
    {
        $origen = $doc->comprobante;

        return match (true) {
            $origen instanceof Nota            => $this->emitirNota($origen),
            $origen instanceof Venta           => $this->emitirVenta($origen),
            $origen instanceof PagoSuscripcion => app(ComprobanteSuscripcionService::class)->reintentar($doc),
            default                            => null,
        };
    }

    /**
     * El timer de la plataforma: lo que no se pudo ni armar (datos null: p. ej. migrado sin contenido o con un error
     * del ERP) se vuelve a armar desde su origen; el resto lo hace el motor (envíos, tickets, webhooks).
     *
     * @return array{rearmados: int, enviados: int, aceptados: int, sin_conexion: bool, tickets: int, webhooks: int}
     */
    public function sincronizar(): array
    {
        $rearmados = 0;
        Documento::with('comprobante')->whereNull('datos')->whereNotNull('comprobante_type')->where('estado', Documento::PENDIENTE)
            ->where(fn ($q) => $q->whereNull('proximo_intento_at')->orWhere('proximo_intento_at', '<=', now()))
            ->orderBy('id')->limit(50)->get()
            ->each(function (Documento $doc) use (&$rearmados) {
                $this->reintentar($doc);
                $rearmados++;
            });

        return ['rearmados' => $rearmados] + $this->emisor->procesar();
    }

    /**
     * Cambió el estado en SUNAT. Si la venta, nota o pago se anuló antes de que SUNAT aceptara su comprobante,
     * ahora que está aceptado se pide la baja (la nota de crédito que anuló un pago de suscripción no se da de baja).
     */
    public function alCambiarEstado(EstadoDocumentoCambiado $evento): void
    {
        $doc = $evento->documento;
        if (! $doc->fueAceptado() || $doc->baja_id || ! $doc->comprobante_type) {
            return;
        }
        $origen = $doc->comprobante;
        if ($origen && method_exists($origen, 'esAnulada') && $origen->esAnulada()
            && ! ($origen instanceof PagoSuscripcion && $doc->tipo === Documento::NOTA_CREDITO)) {
            $this->anular($origen);
        }
    }

    /** Frase corta para el mensaje de la pantalla tras emitir */
    public static function aviso(?Documento $doc): string
    {
        return match (true) {
            ! $doc                                                       => '',
            $doc->fueAceptado()                                          => ' SUNAT: aceptado.',
            $doc->estado === Documento::PENDIENTE && $doc->intentos === 0 => ' SUNAT: firmado, se envía en segundo plano.',
            $doc->estado === Documento::PENDIENTE                        => ' SUNAT: pendiente de envío (se reintentará automáticamente).',
            default => ' SUNAT: ' . mb_strtolower($doc->etiqueta()) . ($doc->mensaje() ? ' — ' . mb_strimwidth($doc->mensaje(), 0, 160, '…') : '') . '.',
        };
    }

    public function registro(Model $origen): ?Documento
    {
        return Documento::where('comprobante_type', $origen->getMorphClass())->where('comprobante_id', $origen->getKey())
            ->comprobantes()->orderBy('id')->first();
    }

    /** Contenido del XML o del CDR */
    public function archivo(Documento $doc, string $cual): ?string
    {
        return $this->emisor->archivo($doc, $cual);
    }

    /**
     * Registra (con los datos que arma $datos en el momento: los reintentos usan siempre los actuales),
     * firma y programa el envío. Un dato inválido deja el documento en "error" con el motivo.
     */
    public function enviarRegistro(Documento $doc, callable $datos): Documento
    {
        if ($doc->fueAceptado() || $doc->estado === Documento::ANULADO || ! $this->activo()) {
            return $doc;
        }

        try {
            [$doc] = $this->emisor->registrar(Empresa::findOrFail($doc->empresa_id), $datos(), $doc->comprobante);
        } catch (ValidationException $e) {
            $doc->update(['estado' => Documento::ERROR, 'ultimo_error' => mb_substr(collect($e->errors())->flatten()->implode(' '), 0, 1000), 'proximo_intento_at' => null]);

            return $doc->refresh();
        }

        return $this->emisor->programarEnvio($doc);
    }

    /* ───────────────────────── Privado ───────────────────────── */

    private function emitir(Venta|Nota $origen, callable $datos): Documento
    {
        $doc = Documento::firstOrCreate(
            ['empresa_id' => $origen->empresa_id, 'tipo' => $origen->tipo, 'serie' => $origen->serie, 'numero' => $origen->numero],
            ['comprobante_type' => $origen->getMorphClass(), 'comprobante_id' => $origen->getKey(), 'estado' => Documento::PENDIENTE, 'fecha_emision' => $origen->fecha_emision],
        );

        return $this->enviarRegistro($doc->setRelation('comprobante', $origen), $datos);
    }

    private function datosVenta(Venta $v): array
    {
        $v->loadMissing(['empresa', 'cliente.tipoDocumento', 'detalles.producto', 'detalles.tipoUnidad', 'detalles.impuesto']);
        $saldo = $v->esCredito() ? $v->saldo() : 0.0;

        return [
            'tipo'                 => $v->tipo,
            'serie'                => $v->serie,
            'numero'               => (int) $v->numero,
            'fecha_emision'        => $v->fecha_emision->format('Y-m-d H:i:s'),
            'moneda'               => $v->moneda ?: 'PEN',
            'igv_porcentaje'       => (float) $v->empresa->igv,
            'precios_incluyen_igv' => (bool) $v->empresa->precios_incluyen_igv,
            'cliente'              => $this->cliente($v),
            'items'                => $v->detalles->map(fn ($d) => [
                'codigo'          => $d->producto?->codigo,
                'descripcion'     => $d->descripcion,
                'unidad'          => $d->tipoUnidad?->codigo_sunat ?: 'NIU',   // catálogo SUNAT 03 (no el código interno: UND, KG…)
                'cantidad'        => (float) $d->cantidad,
                'precio_unitario' => (float) $d->precio_unitario,
                'descuento'       => (float) $d->descuento,
                'afectacion'      => $this->afectacion($d),
                'valor_venta'     => (float) $d->subtotal,
                'igv'             => (float) $d->total_impuestos,
            ])->values()->all(),
            'forma_pago'           => $saldo > 0 && $v->fecha_vencimiento
                ? ['tipo' => 'credito', 'cuotas' => [['monto' => $saldo, 'fecha' => $v->fecha_vencimiento->toDateString()]]]
                : ['tipo' => 'contado'],
            'observaciones'        => $v->observaciones ? mb_substr($v->observaciones, 0, 500) : null,
            'referencia'           => 'venta:' . $v->id,
        ];
    }

    private function datosNota(Nota $n): array
    {
        $n->loadMissing(['venta.empresa', 'venta.cliente.tipoDocumento', 'detalles.tipoUnidad', 'detalles.producto']);
        $v = $n->venta;

        return [
            'tipo'                 => $n->tipo,
            'serie'                => $n->serie,
            'numero'               => (int) $n->numero,
            'fecha_emision'        => $n->fecha_emision->format('Y-m-d H:i:s'),
            'moneda'               => $n->moneda ?: 'PEN',
            'igv_porcentaje'       => (float) $v->empresa->igv,
            'precios_incluyen_igv' => true,   // precio = total de la línea / cantidad
            'cliente'              => $this->cliente($v),
            'items'                => $n->detalles->map(fn ($d) => [
                'codigo'          => $d->producto?->codigo,
                'descripcion'     => $d->descripcion,
                'unidad'          => $d->tipoUnidad?->codigo_sunat ?: ($d->producto_id ? 'NIU' : 'ZZ'),
                'cantidad'        => (float) $d->cantidad,
                'precio_unitario' => round((float) $d->total / max((float) $d->cantidad, 0.0001), 6),
                'afectacion'      => (float) $d->total_impuestos > 0 ? '10' : '20',
                'valor_venta'     => (float) $d->subtotal,
                'igv'             => (float) $d->total_impuestos,
            ])->values()->all(),
            'afectado'             => ['tipo' => $v->tipo, 'serie' => $v->serie, 'numero' => (int) $v->numero],
            'motivo'               => ['codigo' => $n->motivo_codigo, 'descripcion' => mb_substr($n->motivo_descripcion, 0, 250)],
            'referencia'           => 'nota:' . $n->id,
        ];
    }

    private function cliente(Venta $v): array
    {
        $c = $v->cliente;
        if (! $c || blank($c->numero_documento)) {
            return ['tipo_doc' => '0', 'numero' => '00000000', 'nombre' => 'CLIENTES VARIOS'];
        }

        return [
            'tipo_doc'  => $c->tipoDocumento?->codigo ?? (strlen($c->numero_documento) === 11 ? '6' : '1'),
            'numero'    => $c->numero_documento,
            'nombre'    => $c->nombre_completo,
            'direccion' => $c->direccion,
            'email'     => filter_var($c->correo, FILTER_VALIDATE_EMAIL) ? $c->correo : null,
        ];
    }

    /** Catálogo 07. El ERP aplica el IGV de la empresa a cada línea: con IGV es gravado */
    private function afectacion($detalle): string
    {
        if ((float) $detalle->total_impuestos > 0) {
            return '10';
        }

        return in_array($detalle->impuesto?->tipo_afectacion, ['20', '30', '40'], true) ? $detalle->impuesto->tipo_afectacion : '20';
    }
}
