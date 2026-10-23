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
        'producto'             => env('SUSCRIPCIONES_PRODUCTO', 'Suscripción RicoPet'),   // + el producto: "Suscripción RicoPet ERP — Plan Pro"
        'dias_para_baja'       => 7,   // después de este plazo, anular un pago emite nota de crédito
    ],

    /*
    | Funcionalidades de la plataforma: [nombre, descripción, rutas (patrones de nombre de ruta)].
    | Cada sistema (producto) tiene funcionalidades BASE (las incluyen todos sus planes) y OPCIONALES (cada plan
    | elige cuáles, en planes.modulos). Una funcionalidad puede ser de varios sistemas (ventas, clientes, productos,
    | facturación…): la empresa tiene la UNIÓN de lo que le dan sus suscripciones vigentes (ERP + WEB = todo junto).
    | Una ruta que no está en ninguna funcionalidad es del núcleo (inicio, perfil, usuarios, series, empresa…).
    | El orden importa: la primera que coincide gana (por eso ventas.notas.* va antes que ventas.*).
    */
    'funcionalidades' => [
        // Compartidas por ERP y WEB
        'notas'              => ['Notas de crédito y débito', 'Corrige, devuelve o anula comprobantes ya emitidos.', ['notas.*', 'ventas.notas.*']],
        'ventas'             => ['Ventas y comprobantes', 'Nueva venta y comprobantes: factura, boleta y ticket.', ['ventas.*']],
        'clientes'           => ['Clientes', 'Cartera de clientes con su historial de compras.', ['clientes.*']],
        'productos'          => ['Productos y categorías', 'Catálogo de productos y servicios, categorías y unidades.', ['productos.*', 'categorias.*', 'unidades.*']],
        'facturacion'        => ['Facturación electrónica', 'Envío de comprobantes a SUNAT, bajas y conexión SUNAT de la empresa.', ['sunat.*', 'config.sunat*']],
        'cuentas_cobrar'     => ['Ventas al crédito y cobranzas', 'Cuentas por cobrar, cobros parciales y estado de cuenta de clientes.', ['cuentas-cobrar.*', 'finanzas.movimientos.*']],
        'reportes_basicos'   => ['Reportes básicos', 'Ventas por período, cobros por método de pago, más vendidos y arqueos de caja.',
            ['reportes.index', 'reportes.ventas', 'reportes.ventas.exportar', 'reportes.metodos-pago', 'reportes.metodos-pago.exportar',
                'reportes.productos-vendidos', 'reportes.productos-vendidos.exportar', 'reportes.caja', 'reportes.caja.exportar']],
        'roles'              => ['Roles personalizados', 'Crea tipos de usuario propios con los permisos de tu negocio.', ['roles.*']],
        'auditoria'          => ['Auditoría', 'Historial de quién hizo qué y cuándo en el sistema.', ['config.auditoria*']],

        // ERP
        'caja'               => ['Caja', 'Apertura y cierre de turno, ingresos, egresos y arqueo; cajas registradoras.', ['caja.*', 'cajas.*']],
        'almacenes'          => ['Almacenes', 'Sedes y depósitos donde se guarda el stock.', ['almacenes.*']],
        'cotizaciones'       => ['Cotizaciones', 'Proformas de productos y servicios que se convierten en venta.', ['cotizaciones.*']],
        'compras'            => ['Compras y proveedores', 'Registro de compras con ingreso al stock y gestión de proveedores.', ['compras.*', 'proveedores.*']],
        'cuentas_pagar'      => ['Cuentas por pagar', 'Compras al crédito y pagos a proveedores.', ['cuentas-pagar.*']],
        'inventario'         => ['Control de inventario', 'Kardex valorizado, ajustes de inventario y sus motivos.', ['kardex.*', 'ajustes.*', 'motivos.*']],
        'reportes_avanzados' => ['Reportes avanzados', 'Utilidad, inventario valorizado, reposición, rotación, compras por proveedor, vendedores y registro de ventas.',
            ['reportes.ventas-vendedor*', 'reportes.utilidad*', 'reportes.inventario*', 'reportes.reposicion*', 'reportes.sin-rotacion*',
                'reportes.compras-proveedor*', 'reportes.registro-ventas*']],
        'ordenes_compra'     => ['Órdenes de compra', 'Pedidos a proveedores con recepción parcial o total.', ['ordenes.*']],
        'multialmacen'       => ['Transferencias entre almacenes', 'Mueve mercadería entre locales o almacenes.', ['transferencias.*']],
        'lotes'              => ['Lotes y vencimientos', 'Control por lote con fechas de vencimiento y alertas.', ['lotes.*']],
        'fraccionamientos'   => ['Fraccionamientos', 'Parte presentaciones grandes en unidades (sacos en kilos, cajas en tabletas).', ['fraccionamientos.*']],

        // WEB
        'web_tienda'         => ['Tienda en línea', 'Carrito, pedidos, pago con tarjeta y envíos por zona.', ['web.pedidos.*', 'web.zonas.*']],
        'web_catalogo'       => ['Página web', 'La página de la empresa con sus productos publicados, categorías y datos de contacto.', ['web.*']],
    ],

    /*
    | Qué funcionalidades tiene cada sistema. base: todos sus planes; opcionales: las que elige cada plan.
    | Sin ninguna suscripción vigente (empresas anteriores a los planes, sin_plan_ilimitado): todo el ERP.
    | WEB sin "inventario": el stock se edita directamente en el producto.
    */
    'productos' => [
        'erp' => [
            'base'       => ['ventas', 'clientes', 'productos', 'facturacion', 'caja', 'almacenes'],
            'opcionales' => ['notas', 'cuentas_cobrar', 'reportes_basicos', 'cotizaciones', 'compras', 'cuentas_pagar', 'inventario',
                'reportes_avanzados', 'roles', 'ordenes_compra', 'multialmacen', 'lotes', 'fraccionamientos', 'auditoria'],
        ],
        'web' => [
            'base'       => ['web_catalogo', 'ventas', 'clientes', 'productos', 'facturacion'],
            'opcionales' => ['web_tienda', 'reportes_basicos', 'notas', 'cuentas_cobrar', 'roles', 'auditoria'],
        ],
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
