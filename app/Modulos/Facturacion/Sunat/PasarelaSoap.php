<?php

namespace App\Modulos\Facturacion\Sunat;

use App\Modulos\Facturacion\Support\Emisor;
use Greenter\Model\Response\BaseResult;
use Greenter\Model\Response\BillResult;
use Greenter\Model\Response\StatusResult;
use Greenter\Model\Response\SummaryResult;
use Greenter\Ws\Services\BillSender;
use Greenter\Ws\Services\ConsultCdrService;
use Greenter\Ws\Services\ExtService;
use Greenter\Ws\Services\SoapClient;
use Greenter\Ws\Services\SummarySender;
use Greenter\Ws\Services\WsdlProvider;
use Throwable;

/**
 * Pasarela real con los servicios SOAP de SUNAT (vía Greenter), con tiempos límite propios y
 * verificación TLS con el bundle de CA configurado.
 */
class PasarelaSoap implements Pasarela
{
    public function enviarComprobante(Emisor $emisor, string $nombre, string $xml): RespuestaSunat
    {
        return $this->llamar(function () use ($emisor, $nombre, $xml) {
            $r = (new BillSender())->setClient($this->cliente($emisor))->send($nombre, $xml);

            return $this->desdeResultado($r);
        });
    }

    public function enviarResumen(Emisor $emisor, string $nombre, string $xml): RespuestaSunat
    {
        return $this->llamar(function () use ($emisor, $nombre, $xml) {
            /** @var SummaryResult $r */
            $r = (new SummarySender())->setClient($this->cliente($emisor))->send($nombre, $xml);
            if ($r->isSuccess() && $r->getTicket()) {
                return new RespuestaSunat(RespuestaSunat::TICKET, ticket: $r->getTicket());
            }

            return $this->desdeError($r);
        });
    }

    public function consultarTicket(Emisor $emisor, string $ticket): RespuestaSunat
    {
        return $this->llamar(function () use ($emisor, $ticket) {
            /** @var StatusResult $r */
            $r = (new ExtService())->setClient($this->cliente($emisor))->getStatus($ticket);
            if ((int) $r->getCode() === 98) {
                return new RespuestaSunat(RespuestaSunat::EN_PROCESO, '98', 'SUNAT aún procesa el ticket.');
            }

            return $this->desdeResultado($r);
        });
    }

    public function consultarCdr(Emisor $emisor, string $tipo, string $serie, int $numero): RespuestaSunat
    {
        $url = config('facturacion.endpoints.' . $emisor->modo() . '.consulta_cdr');
        if (! $url) {
            return new RespuestaSunat(RespuestaSunat::ERROR, null, 'La consulta de CDR solo existe en producción.');
        }

        return $this->llamar(function () use ($emisor, $tipo, $serie, $numero, $url) {
            $r = (new ConsultCdrService())->setClient($this->cliente($emisor, WsdlProvider::getConsultPath(), $url))
                ->getStatusCdr($emisor->ruc(), $tipo, $serie, $numero);

            return $this->desdeResultado($r);
        });
    }

    /* ───────────────────────── Privado ───────────────────────── */

    private function cliente(Emisor $emisor, string $wsdl = '', ?string $url = null): SoapClient
    {
        $ssl = ['verify_peer' => false, 'verify_peer_name' => false];
        $ca = config('facturacion.ca_bundle');
        if ($ca) {
            $ruta = str_starts_with($ca, '/') || preg_match('/^[A-Za-z]:/', $ca) ? $ca : base_path($ca);
            if (is_file($ruta)) {
                $ssl = ['verify_peer' => true, 'verify_peer_name' => true, 'cafile' => $ruta];
            }
        }

        $cliente = new SoapClient($wsdl, [
            'stream_context'     => stream_context_create(['ssl' => $ssl, 'http' => ['timeout' => config('facturacion.timeout_respuesta')]]),
            'connection_timeout' => config('facturacion.timeout_conexion'),
            'cache_wsdl'         => WSDL_CACHE_MEMORY,
            'exceptions'         => true,
        ]);
        $cliente->setService($url ?? config('facturacion.endpoints.' . $emisor->modo() . '.comprobantes'));

        [$usuario, $clave] = $emisor->enProduccion()
            ? [$emisor->solUsuario(), $emisor->solClave()]
            : [config('facturacion.beta.usuario'), config('facturacion.beta.clave')];
        $cliente->setCredentials($emisor->ruc() . $usuario, (string) $clave);

        return $cliente;
    }

    /** Ejecuta la llamada con el tiempo límite de lectura; cualquier falla de red queda como "sin conexión" */
    private function llamar(callable $fn): RespuestaSunat
    {
        $anterior = ini_get('default_socket_timeout');
        ini_set('default_socket_timeout', (string) config('facturacion.timeout_respuesta'));
        try {
            return $fn();
        } catch (Throwable $e) {
            return new RespuestaSunat(RespuestaSunat::SIN_CONEXION, 'EXC', $e->getMessage());
        } finally {
            ini_set('default_socket_timeout', (string) $anterior);
        }
    }

    private function desdeResultado(BaseResult $r): RespuestaSunat
    {
        if (! $r->isSuccess() || ! $r instanceof BillResult || ! $r->getCdrResponse()) {
            return $this->desdeError($r);
        }

        $cdr = $r->getCdrResponse();

        return RespuestaSunat::porCodigo((string) (int) $cdr->getCode(), $cdr->getDescription(), $cdr->getNotes() ?? [], $r->getCdrZip());
    }

    private function desdeError(BaseResult $r): RespuestaSunat
    {
        $e = $r->getError();
        $codigo = (string) ($e?->getCode() ?? 'EXC');

        return RespuestaSunat::porCodigo(ctype_digit($codigo) ? (string) (int) $codigo : $codigo, $e?->getMessage() ?: 'Sin respuesta de SUNAT.');
    }
}
