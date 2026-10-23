<?php

return [
    /*
     * Al vender un producto fraccionado (ej. "Ricocan por kilo") sin stock suficiente, el sistema parte
     * automáticamente las unidades necesarias del producto de origen (ej. abre un saco de 15 kg).
     * Queda registrado como fraccionamiento (origen = venta) con su movimiento en el kardex.
     */
    'fraccionar_al_vender' => env('INVENTARIO_FRACCIONAR_AL_VENDER', true),

    // Unidades que se venden en fracciones (la cantidad admite decimales en ventas y compras)
    'unidades_fraccionables' => ['KG', 'LT', 'MT', 'GLN'],
];
