<?php

namespace App\Modulos\Facturacion\Sunat;

use App\Modulos\Facturacion\Support\Emisor;

/** Comunicación con los servicios web de SUNAT (en los tests se reemplaza por una pasarela falsa) */
interface Pasarela
{
    /** sendBill: facturas, boletas y notas → CDR inmediato */
    public function enviarComprobante(Emisor $emisor, string $nombre, string $xml): RespuestaSunat;

    /** sendSummary: comunicación de baja (RA) y resumen diario (RC) → ticket */
    public function enviarResumen(Emisor $emisor, string $nombre, string $xml): RespuestaSunat;

    /** getStatus del ticket */
    public function consultarTicket(Emisor $emisor, string $ticket): RespuestaSunat;

    /** getStatusCdr (solo producción): recupera el CDR de un comprobante ya enviado */
    public function consultarCdr(Emisor $emisor, string $tipo, string $serie, int $numero): RespuestaSunat;
}
