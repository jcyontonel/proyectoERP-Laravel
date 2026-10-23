<?php

use Greenter\Ws\Services\SunatEndpoints;

/*
| Facturación electrónica (módulo Facturacion). El ERP firma los comprobantes con Greenter y los envía
| a SUNAT por sus servicios SOAP. Cada empresa emite con sus propios datos: RUC, modo (beta o producción),
| usuario SOL y certificado digital (Configuración → SUNAT).
*/
return [

    // Apagada: las ventas se registran igual, sin comprobante electrónico
    'activa' => (bool) env('FACTURACION_ACTIVA', true),

    /*
    | "cola": la venta solo firma el XML y el envío a SUNAT lo hace el job EnviarDocumento (la venta no espera).
    | "inmediato": se envía en la misma petición (útil sin worker de colas).
    | El worker corre desde el programador: php artisan schedule:work (o schedule:run cada minuto).
    */
    'envio' => env('FACTURACION_ENVIO', 'cola'),

    /*
    | Servicios de SUNAT por modo. "beta" es el ambiente de pruebas: acepta cualquier RUC con el
    | usuario {RUC}MODDATOS / moddatos y un certificado autofirmado; los comprobantes no tienen validez.
    */
    'endpoints' => [
        'beta' => [
            'comprobantes' => env('SUNAT_BETA_URL', SunatEndpoints::FE_BETA),
            'consulta_cdr' => null,   // SUNAT no ofrece consulta de CDR en beta
        ],
        'produccion' => [
            'comprobantes' => env('SUNAT_PRODUCCION_URL', SunatEndpoints::FE_PRODUCCION),
            'consulta_cdr' => env('SUNAT_CONSULTA_URL', SunatEndpoints::FE_CONSULTA_CDR),
        ],
    ],

    // Credenciales que acepta SUNAT beta para cualquier RUC
    'beta' => ['usuario' => 'MODDATOS', 'clave' => 'moddatos'],

    /*
    | Conexión saliente. El bundle de CA permite verificar el certificado de SUNAT (en este equipo,
    | además, incluye la raíz del antivirus que intercepta HTTPS). Vacío = no se verifica.
    */
    'ca_bundle'         => env('FACTURADOR_CA_BUNDLE', env('APIPERU_CA_BUNDLE', 'storage/certs/cacert.pem')),
    'timeout_conexion'  => (int) env('FACTURADOR_TIMEOUT_CONEXION', 10),
    'timeout_respuesta' => (int) env('FACTURADOR_TIMEOUT_RESPUESTA', 40),

    /*
    | Reintentos: cuando SUNAT no responde o no hay internet, el comprobante queda "pendiente" y
    | facturacion:procesar (cada minuto) lo reenvía con espera creciente (minutos según el intento).
    */
    'reintentos' => [
        'esperas'     => [1, 2, 5, 10, 15, 30, 60],
        'dias_max'    => (int) env('FACTURADOR_DIAS_REINTENTO', 7),   // pasado este plazo queda en "error"
        'por_corrida' => 50,
    ],

    // Consulta del ticket de resúmenes y comunicaciones de baja (minutos entre consultas)
    'tickets' => ['esperas' => [1, 1, 2, 5, 10, 30, 60]],

    // Avisos (webhooks) a los sistemas externos cuando cambia el estado de un comprobante suyo
    'webhooks' => [
        'timeout' => (int) env('FACTURADOR_WEBHOOK_TIMEOUT', 10),
        'esperas' => [1, 5, 15, 60, 180, 720],   // tras el último intento queda "fallido"
    ],

    'igv' => 18.0,

    // Plazo de SUNAT para dar de baja (comunicación de baja / resumen con estado 3)
    'dias_para_anular' => 7,
];
