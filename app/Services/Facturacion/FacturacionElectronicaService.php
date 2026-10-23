<?php

namespace App\Services\Facturacion;

use App\Models\ComprobanteSunat;
use App\Models\Empresa;
use App\Models\Nota;
use App\Models\PagoSuscripcion;
use App\Models\Venta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Envía las facturas, boletas y notas del ERP al facturador electrónico y guarda su estado en
 * comprobantes_sunat. Si el facturador no responde, el comprobante queda pendiente y
 * facturacion:sincronizar lo reintenta; los cambios posteriores llegan por webhook.
 * Nunca interrumpe la venta: los errores quedan registrados en el comprobante.
 */
class FacturacionElectronicaService
{
    private const MOTIVO_ANULACION = 'ANULACION DE LA OPERACION';

    public function __construct(private FacturadorCliente $cliente)
    {
    }

    public function activo(): bool
    {
        return $this->cliente->configurado();
    }

    /** Factura o boleta registrada (los tickets y borradores no van a SUNAT) */
    public function emitirVenta(Venta $venta): ?ComprobanteSunat
    {
        if (! $this->activo() || ! in_array($venta->tipo, ['01', '03'], true) || ! $venta->esCompletada()) {
            return null;
        }

        return $this->emitir($venta, fn () => $this->datosVenta($venta));
    }

    public function emitirNota(Nota $nota): ?ComprobanteSunat
    {
        if (! $this->activo() || $nota->esAnulada()) {
            return null;
        }

        return $this->emitir($nota, fn () => $this->datosNota($nota));
    }

    /** Pide la baja en SUNAT (comunicación de baja o resumen diario) de un comprobante ya aceptado */
    public function anular(Model $doc): ?ComprobanteSunat
    {
        if ($doc instanceof PagoSuscripcion) {   // decide entre baja y nota de crédito según el plazo
            return app(ComprobanteSuscripcionService::class)->anular($doc);
        }

        $reg = $this->registro($doc);

        return $reg ? $this->anularRegistro($reg) : null;
    }

    public function anularRegistro(ComprobanteSunat $reg): ?ComprobanteSunat
    {
        if (! $this->activo()) {
            return null;
        }
        if (! $reg->fueAceptado() || ! $reg->facturador_id) {
            return $reg;   // aún no aceptado: al aceptarse se pedirá la baja (ver aplicar)
        }

        try {
            $resp = $this->cliente->anular($reg->facturador_id, self::MOTIVO_ANULACION);
            $resp->successful()
                ? $this->aplicar($reg, $resp->json())
                : $reg->update(['baja_estado' => ComprobanteSunat::ERROR, 'mensaje_respuesta' => 'Baja no aceptada: ' . $this->errores($resp)]);
        } catch (FacturadorNoDisponible $e) {
            $reg->update(['baja_estado' => 'por_pedir', 'mensaje_respuesta' => $e->getMessage()]);
        }

        return $reg->refresh();
    }

    /** Reenvío manual desde el ERP */
    public function reenviar(ComprobanteSunat $reg): ComprobanteSunat
    {
        if ($reg->facturador_id && $reg->estado !== ComprobanteSunat::RECHAZADO) {
            try {
                $resp = $this->cliente->reenviar($reg->facturador_id);
                $resp->successful() ? $this->aplicar($reg, $resp->json()) : $reg->update(['mensaje_respuesta' => $this->errores($resp)]);
            } catch (FacturadorNoDisponible $e) {
                $reg->update(['mensaje_respuesta' => $e->getMessage()]);
            }

            return $reg->refresh();
        }

        // Rechazado o nunca llegó al facturador: se vuelve a enviar con los datos actuales
        return $this->reintentar($reg) ?? $reg->refresh();
    }

    /** Vuelve a enviar un registro según su origen (venta, nota o pago de suscripción) */
    public function reintentar(ComprobanteSunat $reg): ?ComprobanteSunat
    {
        $doc = $reg->comprobante;

        return match (true) {
            $doc instanceof Nota            => $this->emitirNota($doc),
            $doc instanceof Venta           => $this->emitirVenta($doc),
            $doc instanceof PagoSuscripcion => app(ComprobanteSuscripcionService::class)->reintentar($reg),
            default                         => null,
        };
    }

    /**
     * El timer del ERP: envía lo que no llegó al facturador, pide las bajas pendientes y, por si se
     * perdió un webhook, refresca lo que sigue en proceso.
     *
     * @return array{enviados: int, consultados: int}
     */
    public function sincronizar(): array
    {
        $r = ['enviados' => 0, 'consultados' => 0];

        $porEnviar = ComprobanteSunat::with('comprobante')->whereNull('facturador_id')->where('estado', ComprobanteSunat::PENDIENTE)
            ->where(fn ($q) => $q->whereNull('proximo_intento_at')->orWhere('proximo_intento_at', '<=', now()))
            ->orderBy('id')->limit(50)->get();
        foreach ($porEnviar as $reg) {
            $nuevo = $this->reintentar($reg);
            $r['enviados']++;
            if (! $nuevo || ($nuevo->estado === ComprobanteSunat::PENDIENTE && ! $nuevo->facturador_id)) {
                break;   // el facturador sigue sin responder: no insistir con los demás en esta vuelta
            }
        }

        ComprobanteSunat::where('baja_estado', 'por_pedir')->limit(50)->get()
            ->each(fn (ComprobanteSunat $reg) => $this->anularRegistro($reg));

        $enProceso = ComprobanteSunat::whereNotNull('facturador_id')
            ->where(fn ($q) => $q->whereIn('estado', [ComprobanteSunat::PENDIENTE, ComprobanteSunat::ENVIADO])->orWhereIn('baja_estado', [ComprobanteSunat::PENDIENTE, ComprobanteSunat::ENVIADO]))
            ->where('updated_at', '<=', now()->subMinutes(5))->limit(50)->get();
        foreach ($enProceso as $reg) {
            try {
                $resp = $this->cliente->consultar($reg->facturador_id);
                if ($resp->successful()) {
                    $this->aplicar($reg, $resp->json());
                }
                $reg->touch();
                $r['consultados']++;
            } catch (FacturadorNoDisponible) {
                break;
            }
        }

        return $r;
    }

    /** Registra o actualiza la empresa en el facturador (datos fiscales, modo, SOL y certificado) */
    public function sincronizarEmisor(Empresa $empresa): array
    {
        $datos = [
            'razon_social' => $empresa->razon_social, 'nombre_comercial' => $empresa->nombre_comercial, 'direccion' => (string) $empresa->direccion,
            'ubigeo' => (string) $empresa->ubigeo, 'departamento' => $empresa->departamento, 'provincia' => $empresa->provincia,
            'distrito' => $empresa->distrito, 'email' => $empresa->email, 'telefono' => $empresa->telefono,
            'modo' => $empresa->sunat_modo ?: 'beta', 'sol_usuario' => $empresa->sunat_usuario_sol, 'sol_clave' => $empresa->sunat_clave_sol,
        ];
        if ($empresa->certificado_path && Storage::disk('local')->exists($empresa->certificado_path)) {
            $datos['certificado_pfx'] = base64_encode(Storage::disk('local')->get($empresa->certificado_path));
            $datos['certificado_clave'] = (string) $empresa->certificado_clave;
        } else {
            $datos['quitar_certificado'] = true;
        }

        $resp = $this->cliente->emisor($empresa->ruc, array_filter($datos, fn ($v) => $v !== null));

        return ['ok' => $resp->successful(), 'mensaje' => $resp->successful() ? null : $this->errores($resp), 'emisor' => $resp->json()];
    }

    /** Respuesta del facturador (POST, GET o webhook) → comprobantes_sunat */
    public function aplicar(ComprobanteSunat $reg, array $r): void
    {
        $reg->update([
            'facturador_id'      => $r['id'] ?? $reg->facturador_id,
            'estado'             => $r['estado'] ?? $reg->estado,
            'codigo_respuesta'   => $r['sunat']['codigo'] ?? null,
            'mensaje_respuesta'  => $r['sunat']['descripcion'] ?? ($r['envio']['ultimo_error'] ?? null),
            'observaciones'      => $r['sunat']['observaciones'] ?? null ?: null,
            'hash'               => $r['hash'] ?? $reg->hash,
            'nombre_archivo'     => $r['nombre_archivo'] ?? $reg->nombre_archivo,
            'ticket'             => $r['baja']['ticket'] ?? $reg->ticket,
            'baja_estado'        => $r['baja']['estado'] ?? ($reg->baja_estado === 'por_pedir' ? 'por_pedir' : $reg->baja_estado),
            'proximo_intento_at' => null,
            'respondido_at'      => now(),
        ]);

        // Se anuló en el ERP antes de que SUNAT lo aceptara: ahora que está aceptado, se pide la baja
        // (la nota de crédito que ya anuló un pago de suscripción no se da de baja)
        $doc = $reg->comprobante;
        if ($reg->fueAceptado() && ! $reg->baja_estado && $doc && $doc->esAnulada()
            && ! ($doc instanceof PagoSuscripcion && $reg->tipo === '07')) {
            $this->anular($doc);
        }
    }

    /** Frase corta para el mensaje de la pantalla tras emitir */
    public static function aviso(?ComprobanteSunat $reg): string
    {
        return match (true) {
            ! $reg                         => '',
            $reg->fueAceptado()            => ' SUNAT: aceptado.',
            $reg->estado === ComprobanteSunat::PENDIENTE => ' SUNAT: pendiente de envío (se reintentará automáticamente).',
            default                        => ' SUNAT: ' . mb_strtolower($reg->etiqueta()) . ($reg->mensaje_respuesta ? ' — ' . mb_strimwidth($reg->mensaje_respuesta, 0, 160, '…') : '') . '.',
        };
    }

    /** Emite sin interrumpir nunca la operación del ERP (un fallo inesperado queda en el log) */
    public function emitirSeguro(Venta|Nota $doc): ?ComprobanteSunat
    {
        try {
            return $doc instanceof Nota ? $this->emitirNota($doc) : $this->emitirVenta($doc);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    public function registro(Model $doc): ?ComprobanteSunat
    {
        return ComprobanteSunat::where('comprobante_type', $doc->getMorphClass())->where('comprobante_id', $doc->getKey())->first();
    }

    /** Contenido del XML o del CDR */
    public function archivo(ComprobanteSunat $reg, string $cual): ?Response
    {
        if (! $reg->facturador_id) {
            return null;
        }
        $resp = $this->cliente->archivo($reg->facturador_id, $cual);

        return $resp->successful() ? $resp : null;
    }

    /* ───────────────────────── Privado ───────────────────────── */

    private function emitir(Venta|Nota $doc, callable $datos): ComprobanteSunat
    {
        $reg = ComprobanteSunat::firstOrCreate(
            ['empresa_id' => $doc->empresa_id, 'tipo' => $doc->tipo, 'serie' => $doc->serie, 'numero' => $doc->numero],
            ['comprobante_type' => $doc->getMorphClass(), 'comprobante_id' => $doc->getKey(), 'estado' => ComprobanteSunat::PENDIENTE],
        );

        return $this->enviarRegistro($reg, $datos);
    }

    /**
     * Envía al facturador el comprobante del registro (ya numerado). $datos arma el JSON en el momento,
     * así los reintentos usan siempre los datos actuales.
     */
    public function enviarRegistro(ComprobanteSunat $reg, callable $datos, bool $reintentoEmisor = false): ComprobanteSunat
    {
        if ($reg->fueAceptado() || $reg->estado === ComprobanteSunat::ANULADO) {
            return $reg;
        }
        $reg->update(['intentos' => $reg->intentos + 1, 'enviado_at' => now()]);

        try {
            $resp = $this->cliente->emitir($datos());
        } catch (FacturadorNoDisponible $e) {
            $esperas = config('facturador.esperas');
            $reg->update([
                'estado' => ComprobanteSunat::PENDIENTE, 'mensaje_respuesta' => $e->getMessage(),
                'proximo_intento_at' => now()->addMinutes($esperas[min($reg->intentos - 1, count($esperas) - 1)]),
            ]);

            return $reg->refresh();
        }

        if ($resp->status() === 422) {
            // Primera vez de la empresa en el facturador: se registra y se reintenta una vez
            if (! $reintentoEmisor && $resp->json('errors.emisor')) {
                $emisor = $this->sincronizarEmisor(Empresa::findOrFail($reg->empresa_id));
                if ($emisor['ok']) {
                    return $this->enviarRegistro($reg, $datos, true);
                }
                $reg->update(['estado' => ComprobanteSunat::ERROR, 'mensaje_respuesta' => 'No se pudo registrar la empresa en el facturador: ' . $emisor['mensaje']]);

                return $reg->refresh();
            }
            $reg->update(['estado' => ComprobanteSunat::ERROR, 'mensaje_respuesta' => $this->errores($resp), 'proximo_intento_at' => null]);

            return $reg->refresh();
        }

        $this->aplicar($reg, $resp->json());

        return $reg->refresh();
    }

    private function datosVenta(Venta $v): array
    {
        $v->loadMissing(['empresa', 'cliente.tipoDocumento', 'detalles.producto', 'detalles.tipoUnidad', 'detalles.impuesto']);
        $saldo = $v->esCredito() ? $v->saldo() : 0.0;

        return [
            'emisor'               => $v->empresa->ruc,
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
            'emisor'               => $v->empresa->ruc,
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

    private function errores(Response $resp): string
    {
        $errores = collect($resp->json('errors', []))->flatten()->implode(' ');

        return mb_substr($errores ?: ($resp->json('message') ?? 'HTTP ' . $resp->status()), 0, 1000);
    }
}
