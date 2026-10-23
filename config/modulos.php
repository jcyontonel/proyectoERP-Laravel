<?php

/*
| Contextos de la plataforma: dónde se publican las rutas de cada grupo de módulos.
|
|  - Producción: cada contexto puede ir en su subdominio (erp.ricopet.com, admin.ricopet.com…) y la
|    tienda en el dominio principal (ricopet.com). Define *_DOMINIO y deja el prefijo vacío.
|  - Desarrollo (sin subdominios): sin *_DOMINIO, cada contexto usa su prefijo de ruta: la web pública en la raíz
|    (localhost/…/public/ y /{slug}), el ERP en /erp, /plataforma, /suscripciones y /api.
|  - En producción el ERP va en su subdominio con ERP_PREFIJO vacío (erp.ricopet.com).
|
| Para compartir la sesión entre subdominios: SESSION_DOMAIN=.ricopet.com
| Un negocio nuevo (restaurante, cancha…) agrega aquí su contexto y su módulo en app/Modulos.
*/
return [
    'contextos' => [
        // Gestión de las empresas: ERP y lo que lo acompaña (catálogo, facturación, mi suscripción, página web)
        'erp' => [
            'dominio' => env('ERP_DOMINIO'),                       // ej. erp.ricopet.com
            'prefijo' => env('ERP_PREFIJO', 'erp'),                // en desarrollo: /erp (la raíz es la web pública)
        ],
        // Panel del dueño de la plataforma
        'plataforma' => [
            'dominio' => env('PLATAFORMA_DOMINIO'),                // ej. admin.ricopet.com
            'prefijo' => env('PLATAFORMA_PREFIJO', 'plataforma'),
        ],
        // Portal de suscripciones de los titulares
        'suscripciones' => [
            'dominio' => env('SUSCRIPCIONES_DOMINIO'),             // ej. subs.ricopet.com
            'prefijo' => env('SUSCRIPCIONES_PREFIJO', 'suscripciones'),
        ],
        // Web pública: la raíz del dominio principal (directorio de tiendas y cada tienda en /{slug})
        'web' => [
            'dominio' => env('WEB_DOMINIO'),                       // ej. ricopet.com
            'prefijo' => env('WEB_PREFIJO', ''),                   // la raíz: directorio y tiendas en /{slug}
        ],
        // APIs (sesión por token)
        'api' => [
            'dominio' => env('API_DOMINIO'),
            'prefijo' => env('API_PREFIJO', 'api'),
        ],
    ],
];
