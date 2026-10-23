<?php

namespace App\Modulos\Facturacion\Services;

use RuntimeException;

/** El facturador no respondió (sin red, caído, token inválido): el comprobante queda pendiente y se reintenta */
class FacturadorNoDisponible extends RuntimeException
{
}
