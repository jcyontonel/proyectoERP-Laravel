<?php

namespace App\Providers;

use App\Models\Nota;
use App\Models\PagoSuscripcion;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Cache\RateLimiting\Limit;
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

        // Define el rate limiter 'api' que usan las rutas con 'throttle:api'
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
