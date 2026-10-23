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

    /*
    | Comprobante electrónico de cada pago de suscripción (vía el facturador electrónico).
    | El emisor es una empresa del propio ERP (el dueño de la plataforma): se usan su RUC, su
    | configuración SUNAT (modo, SOL, certificado) y sus series F900 / B900 (módulo "suscripcion").
    | Sin emisor configurado, los pagos se registran sin comprobante.
    */
    'facturacion' => [
        'emisor_ruc'           => env('SUSCRIPCIONES_EMISOR_RUC'),          // recomendado: no cambia al volver a sembrar
        'emisor_empresa_id'    => env('SUSCRIPCIONES_EMISOR_EMPRESA_ID'),   // alternativa por id
        'precios_incluyen_igv' => (bool) env('SUSCRIPCIONES_PRECIOS_INCLUYEN_IGV', true),
        'producto'             => env('SUSCRIPCIONES_PRODUCTO', 'Suscripción al ERP RicoPet'),
        'dias_para_baja'       => 7,   // después de este plazo, anular un pago emite nota de crédito
    ],

    /*
    | Módulos que se reparten entre los planes (planes.modulos). Lo que no está aquí es la base y
    | lo tienen todos: inicio, ventas de productos y servicios, clientes, caja, productos, categorías,
    | unidades, facturación electrónica y configuración.
    | Cada módulo: [nombre, descripción, rutas (patrones de nombre de ruta)]. Si el plan no lo incluye,
    | sus ítems del menú no aparecen y sus rutas responden "tu plan no incluye…".
    */
    'modulos' => [
        'notas'              => ['Notas de crédito y débito', 'Corrige, devuelve o anula comprobantes ya emitidos.', ['notas.*', 'ventas.notas.*']],
        'cuentas_cobrar'     => ['Ventas al crédito y cobranzas', 'Cuentas por cobrar, cobros parciales y estado de cuenta de clientes.', ['cuentas-cobrar.*', 'finanzas.movimientos.*']],
        'reportes_basicos'   => ['Reportes básicos', 'Ventas por período, cobros por método de pago, más vendidos y arqueos de caja.',
            ['reportes.index', 'reportes.ventas', 'reportes.ventas.exportar', 'reportes.metodos-pago', 'reportes.metodos-pago.exportar',
                'reportes.productos-vendidos', 'reportes.productos-vendidos.exportar', 'reportes.caja', 'reportes.caja.exportar']],
        'cotizaciones'       => ['Cotizaciones', 'Proformas de productos y servicios que se convierten en venta.', ['cotizaciones.*']],
        'compras'            => ['Compras y proveedores', 'Registro de compras con ingreso al stock y gestión de proveedores.', ['compras.*', 'proveedores.*']],
        'cuentas_pagar'      => ['Cuentas por pagar', 'Compras al crédito y pagos a proveedores.', ['cuentas-pagar.*']],
        'inventario'         => ['Control de inventario', 'Kardex valorizado, ajustes de inventario y sus motivos.', ['kardex.*', 'ajustes.*', 'motivos.*']],
        'reportes_avanzados' => ['Reportes avanzados', 'Utilidad, inventario valorizado, reposición, rotación, compras por proveedor, vendedores y registro de ventas.',
            ['reportes.ventas-vendedor*', 'reportes.utilidad*', 'reportes.inventario*', 'reportes.reposicion*', 'reportes.sin-rotacion*',
                'reportes.compras-proveedor*', 'reportes.registro-ventas*']],
        'roles'              => ['Roles personalizados', 'Crea tipos de usuario propios con los permisos de tu negocio.', ['roles.*']],
        'ordenes_compra'     => ['Órdenes de compra', 'Pedidos a proveedores con recepción parcial o total.', ['ordenes.*']],
        'multialmacen'       => ['Transferencias entre almacenes', 'Mueve mercadería entre locales o almacenes.', ['transferencias.*']],
        'lotes'              => ['Lotes y vencimientos', 'Control por lote con fechas de vencimiento y alertas.', ['lotes.*']],
        'fraccionamientos'   => ['Fraccionamientos', 'Parte presentaciones grandes en unidades (sacos en kilos, cajas en tabletas).', ['fraccionamientos.*']],
        'auditoria'          => ['Auditoría', 'Historial de quién hizo qué y cuándo en el sistema.', ['config.auditoria*']],
    ],

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
