<?php

return [
    // El IGV, "precios incluyen IGV" y "controlar stock" se leen de la tabla `empresas`.

    // Dígitos del número correlativo: B001-000129
    'numero_pad' => 6,

    // Si tu columna ventas.cliente_id es NOT NULL, crea un cliente "Clientes varios" y pon aquí su id
    'cliente_generico_id' => null,

    // Moneda que se imprime en "SON: ... SOLES"
    'moneda_nombre' => 'SOLES',

    // Máximo de filas al exportar (CSV / impresión)
    'export_max' => 10000,

    // Códigos SUNAT de ventas.tipo / correlativos.tipo
    'tipos_comprobante' => [
        '01' => 'Factura',
        '03' => 'Boleta de venta',
        '07' => 'Nota de crédito',
        '08' => 'Nota de débito',
    ],

    // ventas.metodo_pago (varchar)
    'metodos_pago' => [
        'efectivo'      => 'Efectivo',
        'yape'          => 'Yape',
        'plin'          => 'Plin',
        'transferencia' => 'Transferencia',
        'tarjeta'       => 'Tarjeta',
    ],
];
