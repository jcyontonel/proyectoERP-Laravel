<?php

/*
| Facturador electrónico (ricopet/facturacion). Sin URL o sin token, la facturación electrónica
| queda apagada y las ventas se registran igual (sin envío a SUNAT).
| El token y el secreto se generan en el facturador: php artisan facturador:sistema "ERP RicoPet"
*/
return [
    'url'             => env('FACTURADOR_URL'),
    'token'           => env('FACTURADOR_TOKEN'),
    'webhook_secreto' => env('FACTURADOR_WEBHOOK_SECRETO'),

    // El facturador envía a SUNAT en la misma llamada: el tiempo de respuesta incluye el de SUNAT
    'timeout'          => (int) env('FACTURADOR_TIMEOUT', 45),
    'timeout_conexion' => (int) env('FACTURADOR_TIMEOUT_CONEXION', 3),

    // Si el facturador no responde, facturacion:sincronizar reintenta (minutos según el intento)
    'esperas' => [1, 2, 5, 10, 30, 60],

    // Bundle de CA si el facturador está en https (mismo que APIPeru en este equipo)
    'ca_bundle' => env('FACTURADOR_CA_BUNDLE', env('APIPERU_CA_BUNDLE')),
];
