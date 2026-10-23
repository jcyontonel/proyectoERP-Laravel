<?php

namespace App\Providers;

use App\Modulos\Erp\Models\Nota;
use App\Modulos\Suscripciones\Models\PagoSuscripcion;
use App\Modulos\Nucleo\Models\User;
use App\Modulos\Erp\Models\Venta;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Alias estables de los modelos que se guardan en columnas polimórficas
     * (comprobantes_sunat.comprobante_type, model_has_roles.model_type, personal_access_tokens…).
     * Así la base no depende del namespace: mover un modelo de carpeta no rompe sus registros.
     * Un modelo nuevo que se use en una relación polimórfica debe agregarse aquí.
     */
    public const MORPH_MAP = [
        'user'             => User::class,
        'venta'            => Venta::class,
        'nota'             => Nota::class,
        'pago_suscripcion' => PagoSuscripcion::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Relation::morphMap(self::MORPH_MAP);

        // Los modelos viven en app/Modulos/*/Models: la factory se busca por el nombre corto (database/factories)
        Factory::guessFactoryNamesUsing(fn (string $modelo) => 'Database\\Factories\\' . class_basename($modelo) . 'Factory');

        // Define el rate limiter 'api' que usan las rutas con 'throttle:api'
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
