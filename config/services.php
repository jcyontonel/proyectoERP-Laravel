<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Consulta de DNI (RENIEC) y RUC (SUNAT). El token NUNCA se envía al navegador.
    // API de administración que usa el proyecto admin (ricopet/admin). Sin token, la API queda cerrada.
    'admin' => [
        'token' => env('ERP_ADMIN_TOKEN'),
    ],

    'apiperu' => [
        'url'     => env('APIPERU_URL', 'https://api.apiperu.dev'),
        'token'   => env('APIPERU_TOKEN'),
        'timeout' => (int) env('APIPERU_TIMEOUT', 8),
        // Días que se guarda en caché cada consulta (ahorra cuota del plan)
        'cache_dias' => (int) env('APIPERU_CACHE_DIAS', 30),
        // Certificados raíz para HTTPS cuando PHP no los trae actualizados (XAMPP en Windows: error SSL 60)
        'ca_bundle' => env('APIPERU_CA_BUNDLE'),
    ],

    /*
    | Culqi (pagos con tarjeta en las tiendas). Cada tienda configura sus propias llaves (el dinero es de la
    | empresa) y elige modo prueba o producción. Estas son las llaves DE PRUEBA de la plataforma (pk_test_/sk_test_)
    | que usan las tiendas en modo prueba mientras no carguen las suyas.
    */
    'culqi' => [
        'llave_publica' => env('CULQI_LLAVE_PUBLICA'),
        'llave_secreta' => env('CULQI_LLAVE_SECRETA'),
        'url'           => env('CULQI_URL', 'https://api.culqi.com/v2'),
        'timeout'       => (int) env('CULQI_TIMEOUT', 25),
        'ca_bundle'     => env('CULQI_CA_BUNDLE', env('APIPERU_CA_BUNDLE')),
    ],

];
