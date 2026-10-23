<?php

namespace App\Modulos\Facturacion\Services;

use App\Modulos\Facturacion\Contracts\EmisorComprobantes;
use App\Modulos\Facturacion\Events\EstadoDocumentoCambiado;
use App\Modulos\Facturacion\Jobs\EnviarDocumento;
use App\Modulos\Facturacion\Models\Documento;
use App\Modulos\Facturacion\Models\SistemaExterno;
use App\Modulos\Facturacion\Sunat\Calculadora;
use App\Modulos\Facturacion\Sunat\CertificadoPrueba;
use App\Modulos\Facturacion\Sunat\ConstructorDocumento;
use App\Modulos\Facturacion\Sunat\Pasarela;
use App\Modulos\Facturacion\Sunat\RespuestaSunat;
use App\Modulos\Nucleo\Models\Empresa;
use Greenter\See;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Ciclo de vida de un comprobante: registrar → firmar → enviar → aplicar la respuesta de SUNAT.
 * Si no hay conexión queda "pendiente" y el timer (procesar) lo reintenta. Cada cambio de estado
 * dispara EstadoDocumentoCambiado (webhooks de sistemas externos, bajas pendientes del ERP).
 */
class MotorFacturacion implements EmisorComprobantes
{
    public function __construct(
        private Pasarela $pasarela,
        private ConstructorDocumento $constructor,
        private CertificadoPrueba $certificadoPrueba,
        private WebhookService $webhooks,
    ) {
    }

    public function registrar(Empresa $empresa, array $d, ?Model $origen = null, ?SistemaExterno $sistema = null): array
    {
        $d += [
            'fecha_emision' => now()->toDateTimeString(), 'moneda' => 'PEN', 'igv_porcentaje' => config('facturacion.igv'),
            'precios_incluyen_igv' => true, 'forma_pago' => ['tipo' => 'contado'],
        ];
        $emisor = (new Documento(['empresa_id' => $empresa->id]))->setRelation('empresa', $empresa)->emisor();
        if ($emisor->enProduccion() && ($faltan = $emisor->faltantesProduccion())) {
            throw ValidationException::withMessages(['emisor' => 'Para emitir en producción falta: ' . implode(', ', $faltan) . '.']);
        }

        $calc = Calculadora::calcular($d['items'], (float) $d['igv_porcentaje'], (bool) $d['precios_incluyen_igv']);
        $total = $calc['totales']['total'];

        // Reglas que dependen de los montos
        $sinDocumento = in_array($d['cliente']['tipo_doc'], ['0', '-'], true);
        if ($d['tipo'] === Documento::BOLETA && $sinDocumento && $total >= 700) {
            throw ValidationException::withMessages(['cliente.numero' => 'Una boleta de S/ 700 o más exige el DNI (u otro documento) del cliente.']);
        }
        if (($d['forma_pago']['tipo'] ?? 'contado') === 'credito') {
            $cuotas = round(array_sum(array_column($d['forma_pago']['cuotas'], 'monto')), 2);
            if ($cuotas > $total) {
                throw ValidationException::withMessages(['forma_pago.cuotas' => "Las cuotas suman S/ {$cuotas}, más que el total (S/ {$total})."]);
            }
        }

        $afectado = isset($d['afectado'])
            ? Documento::where('empresa_id', $empresa->id)->where('tipo', $d['afectado']['tipo'])
                ->where('serie', $d['afectado']['serie'])->where('numero', (int) $d['afectado']['numero'])->first()
            : null;

        $datos = [
            'tipo' => $d['tipo'], 'igv_porcentaje' => (float) $d['igv_porcentaje'], 'precios_incluyen_igv' => (bool) $d['precios_incluyen_igv'],
            'cliente' => $d['cliente'], 'forma_pago' => $d['forma_pago'], 'observaciones' => $d['observaciones'] ?? null,
            'afectado' => $d['afectado'] ?? null, 'motivo' => $d['motivo'] ?? null,
            'items' => $calc['items'], 'totales' => $calc['totales'],
        ];

        return DB::transaction(function () use ($empresa, $d, $datos, $total, $afectado, $origen, $sistema) {
            $existente = Documento::where('empresa_id', $empresa->id)->where('tipo', $d['tipo'])->where('serie', $d['serie'])
                ->where('numero', (int) $d['numero'])->lockForUpdate()->first();

            if ($existente && ! $existente->esReemplazable()) {
                return [$existente, false];   // idempotente: ya fue aceptado, anulado o está en SUNAT
            }
            if ($existente && $sistema && $existente->sistema_id !== $sistema->id) {
                throw ValidationException::withMessages(['numero' => "El {$existente->numeroCompleto()} ya fue emitido por otro sistema de la empresa."]);
            }

            $campos = [
                'fecha_emision' => $d['fecha_emision'], 'moneda' => $d['moneda'], 'cliente_tipo_doc' => $d['cliente']['tipo_doc'],
                'cliente_num_doc' => $d['cliente']['numero'], 'cliente_nombre' => $d['cliente']['nombre'], 'total' => $total,
                'datos' => $datos, 'afectado_id' => $afectado?->id, 'estado' => Documento::PENDIENTE, 'referencia' => $d['referencia'] ?? null,
                'webhook_url' => $d['webhook_url'] ?? null, 'intentos' => 0, 'proximo_intento_at' => now(), 'ultimo_error' => null,
                'sunat_codigo' => null, 'sunat_descripcion' => null, 'sunat_observaciones' => null, 'cdr_path' => null,
            ] + ($origen ? ['comprobante_type' => $origen->getMorphClass(), 'comprobante_id' => $origen->getKey()] : [])
              + ($sistema ? ['sistema_id' => $sistema->id] : []);

            $doc = $existente
                ? tap($existente)->update($campos)
                : Documento::create($campos + ['empresa_id' => $empresa->id, 'tipo' => $d['tipo'], 'serie' => $d['serie'], 'numero' => (int) $d['numero']]);

            $this->firmar($doc->setRelation('empresa', $empresa));

            return [$doc->refresh(), true];
        });
    }

    public function programarEnvio(Documento $doc): Documento
    {
        if ($doc->estado !== Documento::PENDIENTE) {
            return $doc;
        }

        config('facturacion.envio') === 'inmediato' ? $this->enviar($doc) : EnviarDocumento::dispatch($doc->id);

        return $doc->refresh();
    }

    public function enviar(Documento $doc, bool $avisar = true): RespuestaSunat
    {
        $doc->loadMissing('empresa');
        if (! $doc->xml_path && ! $this->firmar($doc)) {
            return new RespuestaSunat(RespuestaSunat::ERROR, null, $doc->ultimo_error);
        }

        $xml = Storage::disk('local')->get($doc->xml_path);
        $doc->update(['intentos' => $doc->intentos + 1, 'enviado_at' => now()]);

        $r = $doc->usaTicket()
            ? $this->pasarela->enviarResumen($doc->emisor(), $doc->nombre_archivo, $xml)
            : $this->pasarela->enviarComprobante($doc->emisor(), $doc->nombre_archivo, $xml);

        return $this->aplicar($doc, $r, $avisar);
    }

    /** Consulta el ticket de un RA/RC */
    public function consultarTicket(Documento $doc): RespuestaSunat
    {
        $doc->update(['intentos' => $doc->intentos + 1]);

        return $this->aplicar($doc, $this->pasarela->consultarTicket($doc->emisor(), $doc->ticket), true);
    }

    public function consultarCdr(Documento $doc): RespuestaSunat
    {
        $r = $this->pasarela->consultarCdr($doc->emisor(), $doc->tipo, $doc->serie, (int) $doc->numero);
        if (in_array($r->resultado, [RespuestaSunat::ACEPTADO, RespuestaSunat::OBSERVADO, RespuestaSunat::RECHAZADO], true)) {
            $this->aplicar($doc, $r, true);
        }

        return $r;
    }

    public function anular(Documento $doc, string $motivo): Documento
    {
        if (! $doc->esComprobante() || ! $doc->fueAceptado()) {
            throw ValidationException::withMessages(['comprobante' => 'Solo se anulan comprobantes aceptados por SUNAT (estado actual: ' . $doc->estado . ').']);
        }
        if ($doc->baja && ! in_array($doc->baja->estado, [Documento::RECHAZADO, Documento::ERROR], true)) {
            throw ValidationException::withMessages(['comprobante' => 'El comprobante ya tiene una anulación en curso o aceptada.']);
        }
        $dias = (int) config('facturacion.dias_para_anular');
        if ($doc->fecha_emision->copy()->startOfDay()->diffInDays(today()) > $dias) {
            throw ValidationException::withMessages(['comprobante' => "SUNAT solo permite anular dentro de los {$dias} días de emitido; usa una nota de crédito."]);
        }

        $baja = DB::transaction(function () use ($doc, $motivo) {
            $tipo = $doc->esDeBoleta() ? Documento::RESUMEN : Documento::BAJA;
            $serie = $tipo . '-' . now()->format('Ymd');
            $n = Documento::where('empresa_id', $doc->empresa_id)->where('tipo', $tipo)->where('serie', $serie)->lockForUpdate()->count() + 1;

            $baja = Documento::create([
                'empresa_id' => $doc->empresa_id, 'sistema_id' => $doc->sistema_id, 'tipo' => $tipo, 'serie' => $serie, 'numero' => $n,
                'fecha_emision' => now(), 'moneda' => $doc->moneda, 'total' => $doc->total, 'afectado_id' => $doc->id,
                'datos' => ['motivo' => mb_substr($motivo, 0, 100)], 'estado' => Documento::PENDIENTE, 'referencia' => $doc->referencia,
                'proximo_intento_at' => now(),
            ]);
            $doc->update(['baja_id' => $baja->id]);
            $this->firmar($baja);

            return $baja;
        });

        $this->programarEnvio($baja->refresh());

        return $doc->refresh();
    }

    public function archivo(Documento $doc, string $cual): ?string
    {
        $ruta = $cual === 'cdr' ? $doc->cdr_path : $doc->xml_path;

        return $ruta && Storage::disk('local')->exists($ruta) ? Storage::disk('local')->get($ruta) : null;
    }

    /**
     * Reenvía pendientes (en orden de emisión), consulta tickets y entrega webhooks.
     * Si un envío falla por conexión, no se intentan los demás en esta vuelta.
     */
    public function procesar(): array
    {
        $res = ['enviados' => 0, 'aceptados' => 0, 'sin_conexion' => false, 'tickets' => 0, 'webhooks' => 0];
        $limite = (int) config('facturacion.reintentos.por_corrida');
        $plazo = now()->subDays((int) config('facturacion.reintentos.dias_max'));

        $pendientes = Documento::with('empresa')->where('estado', Documento::PENDIENTE)->whereNotNull('datos')->whereNull('ticket')
            ->where(fn ($q) => $q->whereNull('proximo_intento_at')->orWhere('proximo_intento_at', '<=', now()))
            ->orderBy('fecha_emision')->orderBy('id')->limit($limite)->get();

        foreach ($pendientes as $doc) {
            if ($doc->fecha_emision->lt($plazo)) {
                $antes = $doc->estado;
                $doc->update(['estado' => Documento::ERROR, 'ultimo_error' => 'Venció el plazo de reintentos (' . config('facturacion.reintentos.dias_max') . ' días) sin poder enviarlo.', 'proximo_intento_at' => null]);
                EstadoDocumentoCambiado::dispatch($doc, $antes, true);

                continue;
            }
            $r = $this->enviar($doc);
            $res['enviados']++;
            if (in_array($r->resultado, [RespuestaSunat::ACEPTADO, RespuestaSunat::OBSERVADO], true)) {
                $res['aceptados']++;
            }
            if ($r->resultado === RespuestaSunat::SIN_CONEXION) {
                $res['sin_conexion'] = true;
                break;
            }
        }

        if (! $res['sin_conexion']) {
            $tickets = Documento::with('empresa')->where('estado', Documento::ENVIADO)->whereNotNull('ticket')
                ->where('proximo_intento_at', '<=', now())->limit($limite)->get();
            foreach ($tickets as $doc) {
                $this->consultarTicket($doc);
                $res['tickets']++;
            }
        }

        $res['webhooks'] = $this->webhooks->procesar();

        return $res;
    }

    /* ───────────────────────── Privado ───────────────────────── */

    /** Genera y guarda el XML firmado; un error aquí (certificado, datos) deja el documento en "error" */
    private function firmar(Documento $doc): bool
    {
        try {
            $modelo = $this->constructor->construir($doc);
            $emisor = $doc->emisor();
            $see = new See();
            $see->setCertificate($emisor->certificadoPem() ?: $this->certificadoPrueba->pem());
            $xml = $see->getXmlSigned($modelo);

            $nombre = $modelo->getName();
            $ruta = "comprobantes/{$emisor->ruc()}/{$nombre}.xml";
            Storage::disk('local')->put($ruta, $xml);
            preg_match('/<ds:DigestValue>([^<]+)<\/ds:DigestValue>/', $xml, $m);

            $doc->update(['nombre_archivo' => $nombre, 'xml_path' => $ruta, 'hash' => $m[1] ?? null]);

            return true;
        } catch (Throwable $e) {
            report($e);
            $doc->update(['estado' => Documento::ERROR, 'ultimo_error' => 'No se pudo generar o firmar el XML: ' . $e->getMessage(), 'proximo_intento_at' => null]);

            return false;
        }
    }

    private function aplicar(Documento $doc, RespuestaSunat $r, bool $avisar): RespuestaSunat
    {
        $antes = $doc->estado;
        $comun = ['sunat_codigo' => $r->codigo, 'sunat_descripcion' => $r->descripcion, 'respondido_at' => now()];

        switch ($r->resultado) {
            case RespuestaSunat::ACEPTADO:
            case RespuestaSunat::OBSERVADO:
            case RespuestaSunat::RECHAZADO:
                $doc->update($comun + [
                    'estado' => $r->resultado, 'sunat_observaciones' => $r->observaciones ?: null, 'cdr_path' => $this->guardarCdr($doc, $r),
                    'proximo_intento_at' => null, 'ultimo_error' => null,
                ]);
                if ($doc->usaTicket() && $r->resultado !== RespuestaSunat::RECHAZADO && $doc->afectado) {
                    $antesAfectado = $doc->afectado->estado;
                    $doc->afectado->update(['estado' => Documento::ANULADO]);
                    EstadoDocumentoCambiado::dispatch($doc->afectado, $antesAfectado, $avisar);
                }
                break;

            case RespuestaSunat::TICKET:
                $doc->update(['estado' => Documento::ENVIADO, 'ticket' => $r->ticket, 'intentos' => 0, 'proximo_intento_at' => now()->addMinute(), 'ultimo_error' => null]);
                break;

            case RespuestaSunat::SIN_CONEXION:
            case RespuestaSunat::EN_PROCESO:
                $esperas = config($doc->estado === Documento::ENVIADO ? 'facturacion.tickets.esperas' : 'facturacion.reintentos.esperas');
                $minutos = $esperas[min(max($doc->intentos - 1, 0), count($esperas) - 1)];
                $doc->update(['ultimo_error' => trim(($r->codigo ? "[{$r->codigo}] " : '') . $r->descripcion), 'proximo_intento_at' => now()->addMinutes($minutos)]);
                break;

            default:   // ERROR
                // 1033: ya estaba registrado (la respuesta anterior se perdió). En producción se recupera el CDR.
                if ($r->codigo === '1033' && $doc->emisor()->enProduccion() && $doc->esComprobante()) {
                    $cdr = $this->pasarela->consultarCdr($doc->emisor(), $doc->tipo, $doc->serie, (int) $doc->numero);
                    if (in_array($cdr->resultado, [RespuestaSunat::ACEPTADO, RespuestaSunat::OBSERVADO], true)) {
                        return $this->aplicar($doc, $cdr, $avisar);
                    }
                }
                $doc->update($comun + ['estado' => Documento::ERROR, 'ultimo_error' => trim(($r->codigo ? "[{$r->codigo}] " : '') . $r->descripcion), 'proximo_intento_at' => null]);
        }

        if ($doc->estado !== $antes) {
            EstadoDocumentoCambiado::dispatch($doc, $antes, $avisar);
        }

        return $r;
    }

    private function guardarCdr(Documento $doc, RespuestaSunat $r): ?string
    {
        if (! $r->cdrZip) {
            return $doc->cdr_path;
        }
        $ruta = "comprobantes/{$doc->emisor()->ruc()}/R-{$doc->nombre_archivo}.zip";
        Storage::disk('local')->put($ruta, $r->cdrZip);

        return $ruta;
    }
}
