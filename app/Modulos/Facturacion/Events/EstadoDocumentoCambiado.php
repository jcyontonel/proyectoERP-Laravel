<?php

namespace App\Modulos\Facturacion\Events;

use App\Modulos\Facturacion\Models\Documento;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Un comprobante cambió de estado en SUNAT (aceptado tras un corte, anulado, rechazado…).
 * $avisar: se avisa al sistema externo que lo emitió (no cuando ya recibe el estado en la respuesta de la API).
 */
class EstadoDocumentoCambiado
{
    use Dispatchable;

    public function __construct(public readonly Documento $documento, public readonly ?string $antes, public readonly bool $avisar = true)
    {
    }
}
