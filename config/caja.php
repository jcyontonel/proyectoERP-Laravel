<?php

return [
    // Para registrar una venta (no borrador) el vendedor debe tener su turno de caja abierto
    'exigir_para_vender' => env('CAJA_EXIGIR_PARA_VENDER', true),

    // Diferencia de arqueo (S/) a partir de la cual el cierre exige una observación
    'tolerancia_diferencia' => 1.00,

    // Denominaciones del arqueo de efectivo (soles)
    'denominaciones' => [
        'billetes' => [200, 100, 50, 20, 10],
        'monedas'  => [5, 2, 1, 0.5, 0.2, 0.1],
    ],

    // Conceptos sugeridos para ingresos y egresos manuales
    'conceptos' => [
        'ingreso' => ['Fondo adicional', 'Cobro de deuda anterior', 'Otros ingresos'],
        'egreso'  => ['Gasto menor', 'Pago de servicios', 'Movilidad', 'Retiro a banco', 'Adelanto de sueldo', 'Otros egresos'],
    ],
];
