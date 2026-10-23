<?php

namespace Tests\Fakes;

use App\Modulos\Facturacion\Sunat\Pasarela;
use App\Modulos\Facturacion\Sunat\RespuestaSunat;
use App\Modulos\Facturacion\Support\Emisor;

/**
 * SUNAT simulado: devuelve las respuestas encoladas (o "aceptado" si no hay) y registra cada llamada.
 */
class PasarelaFalsa implements Pasarela
{
    /** @var array<int, RespuestaSunat> */
    public array $cola = [];

    /** @var array<int, array{metodo: string, nombre: ?string, xml: ?string, ruc: string}> */
    public array $llamadas = [];

    public function encolar(RespuestaSunat ...$r): self
    {
        array_push($this->cola, ...$r);

        return $this;
    }

    public static function sinConexion(): RespuestaSunat
    {
        return new RespuestaSunat(RespuestaSunat::SIN_CONEXION, 'HTTP', 'Could not connect to host');
    }

    public static function aceptado(string $desc = 'ha sido aceptada'): RespuestaSunat
    {
        return new RespuestaSunat(RespuestaSunat::ACEPTADO, '0', $desc, [], 'ZIP-CDR');
    }

    public function enviarComprobante(Emisor $emisor, string $nombre, string $xml): RespuestaSunat
    {
        $this->llamadas[] = ['metodo' => 'sendBill', 'nombre' => $nombre, 'xml' => $xml, 'ruc' => $emisor->ruc()];

        return array_shift($this->cola) ?? self::aceptado();
    }

    public function enviarResumen(Emisor $emisor, string $nombre, string $xml): RespuestaSunat
    {
        $this->llamadas[] = ['metodo' => 'sendSummary', 'nombre' => $nombre, 'xml' => $xml, 'ruc' => $emisor->ruc()];

        return array_shift($this->cola) ?? new RespuestaSunat(RespuestaSunat::TICKET, ticket: '1700000000001');
    }

    public function consultarTicket(Emisor $emisor, string $ticket): RespuestaSunat
    {
        $this->llamadas[] = ['metodo' => 'getStatus', 'nombre' => $ticket, 'xml' => null, 'ruc' => $emisor->ruc()];

        return array_shift($this->cola) ?? self::aceptado('ha sido aceptada (baja)');
    }

    public function consultarCdr(Emisor $emisor, string $tipo, string $serie, int $numero): RespuestaSunat
    {
        $this->llamadas[] = ['metodo' => 'getStatusCdr', 'nombre' => "{$tipo}-{$serie}-{$numero}", 'xml' => null, 'ruc' => $emisor->ruc()];

        return array_shift($this->cola) ?? self::aceptado();
    }

    public function metodos(): array
    {
        return array_column($this->llamadas, 'metodo');
    }
}
