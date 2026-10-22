<?php

return [
    // Periodicidades de pago: clave => [etiqueta, meses]
    'periodicidades' => [
        'mensual'    => ['Mensual', 1],
        'trimestral' => ['Trimestral', 3],
        'anual'      => ['Anual', 12],
    ],

    // Días después del vencimiento en que la empresa aún puede emitir (con aviso)
    'dias_gracia' => (int) env('SUSCRIPCION_DIAS_GRACIA', 5),

    // Días antes del vencimiento en que se genera el cobro pendiente de renovación y se avisa en el ERP
    'dias_aviso' => (int) env('SUSCRIPCION_DIAS_AVISO', 7),

    // Fracción del límite a partir de la cual se avisa en el ERP (0.8 = 80 %)
    'aviso_uso' => 0.8,

    // Empresa sin ninguna suscripción: true = sin límites (empresas anteriores al sistema de planes)
    'sin_plan_ilimitado' => (bool) env('SUSCRIPCION_SIN_PLAN_ILIMITADO', true),

    // Métodos para registrar pagos de suscripción
    'metodos_pago' => [
        'transferencia' => 'Transferencia',
        'deposito'      => 'Depósito',
        'yape'          => 'Yape',
        'plin'          => 'Plin',
        'tarjeta'       => 'Tarjeta',
        'efectivo'      => 'Efectivo',
    ],
];
