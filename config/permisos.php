<?php

/*
| Permisos agrupados para la pantalla de roles (los nombres viven en RolesYPermisosSeeder).
| Un permiso de la base que no esté aquí se muestra en "Otros".
*/
return [
    'grupos' => [
        'Ventas' => [
            'ver_ventas'      => ['Ver ventas', 'Listado, detalle, ticket y exportación de ventas.'],
            'emitir-ventas'   => ['Emitir ventas', 'Registrar ventas y guardar borradores.'],
            'editar_ventas'   => ['Anular ventas', 'Anular comprobantes emitidos (repone el stock).'],
            'eliminar_ventas' => ['Eliminar ventas', 'Eliminar borradores de venta.'],
            'emitir-notas'    => ['Notas y SUNAT', 'Notas de crédito/débito y envíos a SUNAT.'],
        ],
        'Compras' => [
            'gestionar-compras'     => ['Compras', 'Registrar y anular compras y sus pagos.'],
            'gestionar-proveedores' => ['Proveedores', 'Crear y editar proveedores.'],
        ],
        'Inventario' => [
            'gestionar-productos'  => ['Productos', 'Catálogo, precios, categorías y unidades.'],
            'gestionar-inventario' => ['Inventario', 'Ajustes, transferencias, lotes, kardex y fraccionamientos.'],
            'aprobar-ajustes'      => ['Aprobar ajustes', 'Aprobar ajustes que requieren autorización.'],
        ],
        'Caja y finanzas' => [
            'gestionar-caja'  => ['Caja', 'Abrir y cerrar turnos, ingresos y egresos de caja.'],
            'registrar-pagos' => ['Cobros', 'Cuentas por cobrar y cobros. Con compras o reportes, también cuentas por pagar.'],
        ],
        'Clientes y reportes' => [
            'gestionar-clientes' => ['Clientes', 'Registrar, editar y quitar clientes.'],
            'ver-reportes'       => ['Reportes', 'Reportes de ventas, inventario, utilidad y finanzas.'],
        ],
        'Configuración' => [
            'gestionar-empresas'      => ['Empresa', 'Datos de la empresa, almacenes y cajas.'],
            'gestionar-usuarios'      => ['Usuarios y roles', 'Dar acceso a usuarios y definir sus roles.'],
            'ver_usuarios'            => ['Ver usuarios', 'Consultar los usuarios de la empresa.'],
            'crear_usuarios'          => ['Crear usuarios', 'Registrar usuarios nuevos.'],
            'configurar-correlativos' => ['Series', 'Series y correlativos de comprobantes.'],
            'configurar-impuestos'    => ['Impuestos', 'Afectaciones al IGV.'],
        ],
    ],

    // Roles del sistema (empresa_id NULL): no se editan ni eliminan; se pueden duplicar
    'rol_administrador' => 'Administrador',
];
