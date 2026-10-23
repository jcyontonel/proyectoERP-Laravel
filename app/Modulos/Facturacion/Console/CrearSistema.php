<?php

namespace App\Modulos\Facturacion\Console;

use App\Modulos\Facturacion\Models\SistemaExterno;
use App\Modulos\Nucleo\Models\Empresa;
use Illuminate\Console\Command;

/** Da acceso a la API v1 a un sistema externo que emitirá por una empresa. El token se muestra una sola vez. */
class CrearSistema extends Command
{
    protected $signature = 'facturacion:sistema {ruc : RUC de la empresa emisora} {nombre : Nombre del sistema (POS, tienda…)} {--webhook= : URL que recibe los cambios de estado}';

    protected $description = 'Crea el token de un sistema externo para la API v1 de comprobantes';

    public function handle(): int
    {
        $empresa = Empresa::where('ruc', $this->argument('ruc'))->first();
        if (! $empresa) {
            $this->error('No hay una empresa con ese RUC.');

            return self::FAILURE;
        }
        $webhook = $this->option('webhook');
        if ($webhook && ! filter_var($webhook, FILTER_VALIDATE_URL)) {
            $this->error('La URL del webhook no es válida.');

            return self::FAILURE;
        }

        [$sistema, $token] = SistemaExterno::registrar($empresa, $this->argument('nombre'), $webhook);

        $this->info("Sistema «{$sistema->nombre}» creado para {$empresa->razon_social} ({$empresa->ruc}). Guarda estos valores en el sistema externo:");
        $this->line('  FACTURADOR_URL=' . rtrim(config('app.url'), '/') . '/api/v1');
        $this->line("  FACTURADOR_TOKEN={$token}");
        $this->line("  FACTURADOR_WEBHOOK_SECRETO={$sistema->webhook_secreto}");
        $this->warn('El token no se vuelve a mostrar.');

        return self::SUCCESS;
    }
}
