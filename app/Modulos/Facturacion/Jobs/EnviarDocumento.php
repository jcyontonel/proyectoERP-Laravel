<?php

namespace App\Modulos\Facturacion\Jobs;

use App\Modulos\Facturacion\Contracts\EmisorComprobantes;
use App\Modulos\Facturacion\Models\Documento;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Envía un comprobante a SUNAT fuera de la petición (la venta no espera a SUNAT).
 * Un solo intento: si no hay conexión el documento queda pendiente y lo reintenta facturacion:procesar.
 */
class EnviarDocumento implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $documentoId)
    {
    }

    public function handle(EmisorComprobantes $emisor): void
    {
        $doc = Documento::find($this->documentoId);
        if ($doc && $doc->estado === Documento::PENDIENTE && ! $doc->ticket) {
            $emisor->enviar($doc);
        }
    }
}
