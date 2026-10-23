<?php

/*
 * Menú principal del ERP. Lo arma App\Modulos\Nucleo\Support\Menu para el usuario actual.
 *
 *  clave       identificador único (también es la URL de /modulos/{clave} mientras no exista el módulo)
 *  route       nombre de ruta; si la ruta aún no existe, el ítem se muestra como "Pronto"
 *  activo      patrones de nombre de ruta que marcan el ítem como actual (por defecto: route)
 *  permiso     permiso de Spatie necesario (null = cualquier usuario de la empresa)
 *  contador    clave de App\Modulos\Erp\Services\NotificacionService::contadores() para la burbuja
 *  descripcion texto de la página "Próximamente"
 */

return [
    'secciones' => [
        [
            'titulo' => null,
            'items'  => [
                ['clave' => 'inicio', 'label' => 'Inicio', 'icon' => 'home', 'route' => 'inicio'],
            ],
        ],

        [
            'titulo' => 'Ventas',
            'items'  => [
                ['clave' => 'nueva-venta', 'label' => 'Nueva venta', 'icon' => 'plus-circle', 'route' => 'ventas.create', 'permiso' => 'emitir-ventas'],
                ['clave' => 'ventas', 'label' => 'Comprobantes', 'icon' => 'receipt', 'route' => 'ventas.index', 'activo' => ['ventas.index', 'ventas.show', 'ventas.edit'], 'permiso' => 'ver_ventas', 'contador' => 'borradores'],
                ['clave' => 'notas', 'label' => 'Notas de crédito / débito', 'icon' => 'file-minus', 'route' => 'notas.index', 'activo' => ['notas.*', 'ventas.notas.*'], 'permiso' => 'emitir-notas',
                    'descripcion' => 'Emite notas de crédito y débito sobre facturas y boletas: anulaciones, devoluciones, descuentos y penalidades.'],
                ['clave' => 'cotizaciones', 'label' => 'Cotizaciones', 'icon' => 'clipboard', 'route' => 'cotizaciones.index', 'activo' => ['cotizaciones.*'], 'permiso' => 'emitir-ventas',
                    'descripcion' => 'Prepara proformas para tus clientes y conviértelas en venta con un clic.'],
                ['clave' => 'clientes', 'label' => 'Clientes', 'icon' => 'users', 'route' => 'clientes.index', 'activo' => ['clientes.*']],
            ],
        ],

        [
            // Producto "Página web": sin suscripción web no aparece (módulos web_catalogo y web_tienda)
            'titulo' => 'Página web',
            'items'  => [
                ['clave' => 'web-tienda', 'label' => 'Mi tienda', 'icon' => 'globe', 'route' => 'web.tienda.edit', 'activo' => ['web.tienda.*'], 'permiso' => 'gestionar-empresas',
                    'descripcion' => 'Dirección, textos, contacto, entrega y pagos de tu página web.'],
                ['clave' => 'web-productos', 'label' => 'Productos publicados', 'icon' => 'image', 'route' => 'web.productos.index', 'activo' => ['web.productos.*'], 'permiso' => 'gestionar-productos',
                    'descripcion' => 'Elige qué productos se ven en tu página, con foto y destacados.'],
                ['clave' => 'web-pedidos', 'label' => 'Pedidos', 'icon' => 'shopping-cart', 'route' => 'web.pedidos.index', 'activo' => ['web.pedidos.*'], 'permiso' => 'emitir-ventas', 'contador' => 'pedidos_web',
                    'descripcion' => 'Pedidos de tu tienda en línea: confírmalos y conviértelos en venta.'],
                ['clave' => 'web-zonas', 'label' => 'Zonas de envío', 'icon' => 'truck', 'route' => 'web.zonas.index', 'activo' => ['web.zonas.*'], 'permiso' => 'gestionar-empresas',
                    'descripcion' => 'Dónde repartes, el costo y el tiempo de entrega.'],
                ['clave' => 'web-ver', 'label' => 'Ver mi tienda', 'icon' => 'external-link', 'route' => 'web.tienda.ver', 'activo' => [], 'permiso' => 'gestionar-empresas'],
            ],
        ],

        [
            'titulo' => 'Caja',
            'items'  => [
                ['clave' => 'caja', 'label' => 'Apertura y cierre', 'icon' => 'wallet', 'route' => 'caja.index', 'activo' => ['caja.index', 'caja.cierre'], 'permiso' => 'gestionar-caja',
                    'descripcion' => 'Abre tu turno con el monto inicial, registra ingresos y egresos y ciérralo con el arqueo de efectivo.'],
                ['clave' => 'movimientos-caja', 'label' => 'Ingresos y egresos', 'icon' => 'arrow-left-right', 'route' => 'caja.movimientos.index', 'permiso' => 'gestionar-caja',
                    'descripcion' => 'Gastos menores, retiros y depósitos de la caja del turno.'],
                ['clave' => 'turnos', 'label' => 'Historial de turnos', 'icon' => 'history', 'route' => 'caja.turnos.index', 'activo' => ['caja.turnos.*'], 'permiso' => 'gestionar-caja',
                    'descripcion' => 'Consulta los turnos cerrados, sus arqueos y diferencias.'],
            ],
        ],

        [
            'titulo' => 'Compras',
            'items'  => [
                ['clave' => 'compras', 'label' => 'Compras', 'icon' => 'truck', 'route' => 'compras.index', 'activo' => ['compras.*'], 'permiso' => 'gestionar-compras'],
                ['clave' => 'proveedores', 'label' => 'Proveedores', 'icon' => 'building', 'route' => 'proveedores.index', 'activo' => ['proveedores.*'], 'permiso' => 'gestionar-proveedores'],
                ['clave' => 'ordenes-compra', 'label' => 'Órdenes de compra', 'icon' => 'clipboard', 'route' => 'ordenes.index', 'activo' => ['ordenes.*'], 'permiso' => 'gestionar-compras',
                    'descripcion' => 'Pide mercadería a tus proveedores y recíbela parcial o totalmente como compra.'],
            ],
        ],

        [
            'titulo' => 'Inventario',
            'items'  => [
                ['clave' => 'productos', 'label' => 'Productos', 'icon' => 'package', 'route' => 'productos.index', 'activo' => ['productos.*'], 'contador' => 'stock_bajo'],
                ['clave' => 'categorias', 'label' => 'Categorías', 'icon' => 'tag', 'route' => 'categorias.index', 'activo' => ['categorias.*'], 'permiso' => 'gestionar-productos',
                    'descripcion' => 'Organiza tu catálogo por familias de productos.'],
                ['clave' => 'kardex', 'label' => 'Kardex', 'icon' => 'layers', 'route' => 'kardex.index', 'activo' => ['kardex.*'], 'permiso' => 'gestionar-inventario',
                    'descripcion' => 'Historial valorizado de entradas y salidas por producto y almacén.'],
                ['clave' => 'ajustes', 'label' => 'Ajustes de inventario', 'icon' => 'sliders', 'route' => 'ajustes.index', 'activo' => ['ajustes.*'], 'permiso' => 'gestionar-inventario',
                    'descripcion' => 'Conteos físicos, mermas, vencidos y sobrantes, con aprobación cuando el motivo lo exige.'],
                ['clave' => 'transferencias', 'label' => 'Transferencias', 'icon' => 'arrow-left-right', 'route' => 'transferencias.index', 'activo' => ['transferencias.*'], 'permiso' => 'gestionar-inventario',
                    'descripcion' => 'Mueve mercadería entre almacenes con envío y recepción.'],
                ['clave' => 'fraccionamientos', 'label' => 'Fraccionamientos', 'icon' => 'layers', 'route' => 'fraccionamientos.index', 'activo' => ['fraccionamientos.*'], 'permiso' => 'gestionar-inventario'],
                ['clave' => 'lotes', 'label' => 'Lotes y vencimientos', 'icon' => 'calendar-clock', 'route' => 'lotes.index', 'activo' => ['lotes.*'], 'permiso' => 'gestionar-inventario', 'contador' => 'por_vencer',
                    'descripcion' => 'Controla los lotes de cada producto y lo que está por vencer.'],
            ],
        ],

        [
            'titulo' => 'Finanzas',
            'items'  => [
                ['clave' => 'cuentas-cobrar', 'label' => 'Cuentas por cobrar', 'icon' => 'coins', 'route' => 'cuentas-cobrar.index', 'activo' => ['cuentas-cobrar.*'], 'permiso' => 'registrar-pagos', 'contador' => 'por_cobrar_vencidas',
                    'descripcion' => 'Ventas al crédito, cuotas, cobros parciales y clientes morosos.'],
                ['clave' => 'cuentas-pagar', 'label' => 'Cuentas por pagar', 'icon' => 'landmark', 'route' => 'cuentas-pagar.index', 'activo' => ['cuentas-pagar.*'], 'permiso' => 'cuentas-por-pagar', 'contador' => 'por_pagar_vencidas',
                    'descripcion' => 'Compras pendientes de pago a proveedores y su calendario de vencimientos.'],
                ['clave' => 'movimientos-finanzas', 'label' => 'Cobros y pagos', 'icon' => 'wallet', 'route' => 'finanzas.movimientos.index', 'activo' => ['finanzas.movimientos.*'], 'permiso' => 'registrar-pagos',
                    'descripcion' => 'Historial de cobros y pagos registrados, con anulación y exportación.'],
            ],
        ],

        [
            'titulo' => 'Facturación electrónica',
            'items'  => [
                ['clave' => 'sunat', 'label' => 'Envíos a SUNAT', 'icon' => 'upload-cloud', 'route' => 'sunat.index', 'permiso' => 'emitir-notas', 'contador' => 'sunat_pendientes',
                    'descripcion' => 'Estado de cada comprobante electrónico: aceptado, observado o rechazado, con su XML y CDR.'],
                ['clave' => 'resumenes', 'label' => 'Resúmenes y bajas', 'icon' => 'file-text', 'route' => 'sunat.resumenes', 'permiso' => 'emitir-notas',
                    'descripcion' => 'Resúmenes diarios de boletas y comunicaciones de baja.'],
            ],
        ],

        [
            'titulo' => 'Reportes',
            'items'  => [
                ['clave' => 'reportes', 'label' => 'Todos los reportes', 'icon' => 'layers', 'route' => 'reportes.index', 'permiso' => 'ver-reportes'],
                ['clave' => 'reporte-ventas', 'label' => 'Ventas', 'icon' => 'bar-chart', 'route' => 'reportes.ventas', 'activo' => ['reportes.ventas*', 'reportes.metodos-pago*'], 'permiso' => 'ver-reportes'],
                ['clave' => 'reporte-productos', 'label' => 'Más vendidos', 'icon' => 'trending-up', 'route' => 'reportes.productos-vendidos', 'activo' => ['reportes.productos-vendidos*'], 'permiso' => 'ver-reportes'],
                ['clave' => 'reporte-utilidad', 'label' => 'Utilidad', 'icon' => 'pie-chart', 'route' => 'reportes.utilidad', 'activo' => ['reportes.utilidad*'], 'permiso' => 'ver-reportes'],
                ['clave' => 'reporte-inventario', 'label' => 'Inventario valorizado', 'icon' => 'warehouse', 'route' => 'reportes.inventario', 'activo' => ['reportes.inventario*', 'reportes.reposicion*', 'reportes.sin-rotacion*'], 'permiso' => 'ver-reportes'],
                ['clave' => 'reporte-ple', 'label' => 'Registro de ventas', 'icon' => 'download', 'route' => 'reportes.registro-ventas', 'activo' => ['reportes.registro-ventas*'], 'permiso' => 'ver-reportes'],
            ],
        ],

        [
            'titulo' => 'Configuración',
            'config' => true, // también se muestra en el menú del engranaje
            'items'  => [
                ['clave' => 'suscripcion', 'label' => 'Mi suscripción', 'icon' => 'sparkles', 'route' => 'config.suscripcion', 'activo' => ['config.suscripcion'], 'permiso' => 'gestionar-empresas',
                    'descripcion' => 'Plan contratado, vigencia, consumo de los límites y pagos.'],
                ['clave' => 'empresa', 'label' => 'Datos de la empresa', 'icon' => 'store', 'route' => 'config.empresa', 'activo' => ['config.empresa*'], 'permiso' => 'gestionar-empresas',
                    'descripcion' => 'RUC, dirección, logo, IGV, precios con IGV y control de stock.'],
                ['clave' => 'usuarios', 'label' => 'Usuarios y roles', 'icon' => 'shield', 'route' => 'usuarios.index', 'activo' => ['usuarios.*', 'roles.*'], 'permiso' => 'gestionar-usuarios',
                    'descripcion' => 'Invita usuarios a tu empresa y define su rol y permisos.'],
                ['clave' => 'series', 'label' => 'Series y correlativos', 'icon' => 'hash', 'route' => 'series.index', 'activo' => ['series.*'], 'permiso' => 'configurar-correlativos',
                    'descripcion' => 'Series de facturas, boletas, notas y documentos internos.'],
                ['clave' => 'almacenes', 'label' => 'Almacenes', 'icon' => 'warehouse', 'route' => 'almacenes.index', 'activo' => ['almacenes.*'], 'permiso' => 'gestionar-empresas'],
                ['clave' => 'cajas', 'label' => 'Cajas registradoras', 'icon' => 'wallet', 'route' => 'cajas.index', 'activo' => ['cajas.*'], 'permiso' => 'gestionar-empresas'],
                ['clave' => 'impuestos', 'label' => 'Impuestos', 'icon' => 'percent', 'route' => 'impuestos.index', 'activo' => ['impuestos.*'], 'permiso' => 'configurar-impuestos',
                    'descripcion' => 'Afectaciones al IGV: gravado, exonerado, inafecto y exportación.'],
                ['clave' => 'unidades', 'label' => 'Unidades de medida', 'icon' => 'ruler', 'route' => 'unidades.index', 'activo' => ['unidades.*'], 'permiso' => 'gestionar-productos',
                    'descripcion' => 'Unidades y su código SUNAT (catálogo 03).'],
                ['clave' => 'motivos-ajuste', 'label' => 'Motivos de ajuste', 'icon' => 'list-checks', 'route' => 'motivos.index', 'activo' => ['motivos.*'], 'permiso' => 'gestionar-inventario',
                    'descripcion' => 'Razones de los ajustes de inventario y si requieren aprobación.'],
                ['clave' => 'sunat-config', 'label' => 'Conexión SUNAT', 'icon' => 'lock', 'route' => 'config.sunat', 'activo' => ['config.sunat*'], 'permiso' => 'gestionar-empresas',
                    'descripcion' => 'Usuario SOL, certificado digital y modo beta o producción.'],
                ['clave' => 'auditoria', 'label' => 'Auditoría', 'icon' => 'eye', 'route' => 'config.auditoria', 'activo' => ['config.auditoria*'], 'permiso' => 'gestionar-empresas',
                    'descripcion' => 'Quién hizo qué y cuándo: creaciones, cambios y anulaciones.'],
            ],
        ],

        [
            'titulo' => 'Plataforma',
            'items'  => [
                ['clave' => 'plataforma', 'label' => 'Administrar plataforma', 'icon' => 'shield', 'route' => 'plataforma.inicio', 'activo' => ['plataforma.*'], 'permiso' => 'administrar-plataforma',
                    'descripcion' => 'Empresas, planes, ofertas, suscripciones y pagos de todas las empresas.'],
            ],
        ],

        [
            'titulo' => 'Mi cuenta',
            'oculta' => true, // no va en la barra lateral: se usa en el menú de usuario y la ayuda
            'items'  => [
                ['clave' => 'perfil', 'label' => 'Mi perfil', 'icon' => 'user', 'route' => 'perfil.edit',
                    'descripcion' => 'Tus datos, contraseña y la empresa con la que inicias por defecto.'],
                ['clave' => 'preferencias', 'label' => 'Preferencias', 'icon' => 'sliders', 'route' => 'perfil.preferencias',
                    'descripcion' => 'Impresora de tickets, formato de impresión, atajos de teclado y notificaciones.'],
                ['clave' => 'ayuda', 'label' => 'Centro de ayuda', 'icon' => 'help-circle', 'route' => 'ayuda.index',
                    'descripcion' => 'Guías paso a paso, preguntas frecuentes y contacto con soporte.'],
                ['clave' => 'novedades', 'label' => 'Novedades', 'icon' => 'sparkles', 'route' => 'ayuda.novedades',
                    'descripcion' => 'Lo nuevo en cada versión del sistema.'],
            ],
        ],
    ],
];
