<?php

namespace App\Modulos\Facturacion\Providers;

use App\Modulos\Facturacion\Console\CrearSistema;
use App\Modulos\Facturacion\Console\ImportarFacturador;
use App\Modulos\Facturacion\Console\Procesar;
use App\Modulos\Facturacion\Contracts\EmisorComprobantes;
use App\Modulos\Facturacion\Events\EstadoDocumentoCambiado;
use App\Modulos\Facturacion\Services\FacturacionElectronicaService;
use App\Modulos\Facturacion\Services\MotorFacturacion;
use App\Modulos\Facturacion\Services\WebhookService;
use App\Modulos\Facturacion\Sunat\Pasarela;
use App\Modulos\Facturacion\Sunat\PasarelaSoap;
use App\Modulos\Nucleo\Providers\ProveedorModulo;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;

/**
 * Facturación electrónica: firma con Greenter y envío directo a SUNAT, estados, bajas, conexión SUNAT
 * de la empresa y la API v1 para sistemas externos.
 */
class FacturacionServiceProvider extends ProveedorModulo
{
    public function register(): void
    {
        $this->app->bind(Pasarela::class, PasarelaSoap::class);
        $this->app->bind(EmisorComprobantes::class, MotorFacturacion::class);
    }

    public function boot(): void
    {
        Event::listen(EstadoDocumentoCambiado::class, function (EstadoDocumentoCambiado $e) {
            if ($e->avisar && $e->documento->sistema_id) {
                app(WebhookService::class)->programar($e->documento);
            }
            app(FacturacionElectronicaService::class)->alCambiarEstado($e);
        });

        if ($this->app->runningInConsole()) {
            $this->commands([Procesar::class, CrearSistema::class, ImportarFacturador::class]);
        }
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('facturacion:procesar')->everyMinute()->withoutOverlapping(10);
        });

        $this->cargarRecursos('facturacion');
        $this->rutasWeb('web.php');
        $this->rutasApi('api.php');
    }
}
