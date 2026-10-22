<?php

return [
    // Cómo se actualiza productos.costo_unitario al registrar una compra:
    //   'ultimo'   → el costo (sin IGV) de la última compra
    //   'promedio' → promedio ponderado con el stock actual (todos los almacenes)
    'costeo' => 'ultimo',

    // compras.tipo_comprobante (documento que emite el proveedor)
    'tipos_comprobante' => [
        'factura' => 'Factura',
        'boleta'  => 'Boleta de venta',
        'guia'    => 'Guía de remisión',
        'recibo'  => 'Recibo por honorarios',
        'otro'    => 'Otro / sin comprobante',
    ],

    // Solo soles: la tabla no guarda tipo de cambio, así que mezclar monedas dañaría el costo y el kardex
    'monedas' => ['PEN' => 'Soles (PEN)'],

    // pagos.metodo_pago al pagar a proveedores
    'metodos_pago' => [
        'efectivo'      => 'Efectivo',
        'transferencia' => 'Transferencia',
        'yape'          => 'Yape',
        'plin'          => 'Plin',
        'tarjeta'       => 'Tarjeta',
        'cheque'        => 'Cheque',
    ],

    // Máximo de filas al exportar
    'export_max' => 10000,
];
