<?php

/* Etiquetas y colores de las pantallas de Plataforma (los valores de negocio vienen de /api/admin/catalogos) */
return [
    // Estado efectivo de la suscripción => [tono del badge, etiqueta]
    'estados' => [
        'prueba'     => ['info', 'En prueba'],
        'activa'     => ['success', 'Activa'],
        'vencida'    => ['warning', 'Vencida (en gracia)'],
        'suspendida' => ['danger', 'Suspendida'],
        'cancelada'  => ['neutral', 'Cancelada'],
        'sin_plan'   => ['neutral', 'Sin plan'],
    ],

    'estados_pago' => [
        'pendiente' => ['warning', 'Pendiente'],
        'pagado'    => ['success', 'Pagado'],
        'anulado'   => ['neutral', 'Anulado'],
    ],

    'periodicidades' => ['mensual' => 'Mensual', 'trimestral' => 'Trimestral', 'anual' => 'Anual'],
];
