<?php

namespace App\Modulos\Facturacion\Console;

use App\Modulos\Facturacion\Services\FacturacionElectronicaService;
use Illuminate\Console\Command;

/**
 * El timer de la facturación electrónica (cada minuto desde el programador): reenvía lo pendiente
 * (cortes de internet o de SUNAT), consulta los tickets de bajas y resúmenes y entrega los webhooks.
 */
class Procesar extends Command
{
    protected $signature = 'facturacion:procesar';

    protected $description = 'Reenvía a SUNAT lo pendiente, consulta tickets de bajas y entrega webhooks';

    public function handle(FacturacionElectronicaService $facturacion): int
    {
        if (! $facturacion->activo()) {
            $this->warn('Facturación electrónica apagada (FACTURACION_ACTIVA=false).');

            return self::SUCCESS;
        }

        $r = $facturacion->sincronizar();
        $this->info("Rearmados: {$r['rearmados']} · enviados: {$r['enviados']} · aceptados: {$r['aceptados']}"
            . ($r['sin_conexion'] ? ' · sin conexión con SUNAT' : '') . " · tickets: {$r['tickets']} · webhooks: {$r['webhooks']}.");

        return self::SUCCESS;
    }
}
