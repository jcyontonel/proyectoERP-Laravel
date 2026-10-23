<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Env;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

// El .env no se copia al entorno del proceso (putenv): con varios proyectos Laravel en el mismo Apache
// (ERP y admin), los valores de uno se filtrarían al otro (base de datos, caché, APP_KEY).
Env::disablePutenv();

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // definir el grupo "api" (añade lo que necesites)
        $middleware->group('api', [
            EnsureFrontendRequestsAreStateful::class, // útil para Sanctum (SPAs / cookies). Si usas sólo token Bearer puedes omitirlo.
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);

        // Roles y permisos según la empresa actual (Spatie teams)
        // y suscripción suspendida o cancelada = solo lectura
        $middleware->web(append: [
            \App\Modulos\Nucleo\Http\Middleware\EstablecerEmpresaActual::class,
            \App\Modulos\Suscripciones\Http\Middleware\VerificarSuscripcion::class,
        ]);

        // aliases para usar en rutas ->middleware('role:admin') etc.
        $middleware->alias([
            'auth' => \App\Modulos\Nucleo\Http\Middleware\Authenticate::class,
            'auth:sanctum' => \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Claves que nunca vuelven al formulario (ni a la sesión) tras un error de validación
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password', 'password_actual', 'sunat_clave_sol', 'certificado_clave']);
    })->create();
