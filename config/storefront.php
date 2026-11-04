<?php

/*
| Tiendas públicas (escaparate). Cada tienda tiene un DISEÑO propio (portada,
| secciones y ambiente, hechos a medida en resources/views/storefront/designs/<clave>)
| y una PASARELA de productos común que muestra los artículos asignados a la tienda.
| Esquema completo: docs/tiendas/README.md.
*/
return [

    /*
    | Cómo se forman las direcciones de las tiendas:
    | - "subdomain": farruquito.clavedealma.es (producción; requiere DNS comodín
    |   y SESSION_DOMAIN=.clavedealma.es para que la sesión del panel valga en las tiendas).
    | - "path": clavedealma.es/tienda/farruquito (pruebas en Hostinger y en local).
    | Las dos rutas existen siempre; esto elige cuál se usa en los enlaces.
    */
    'url_mode' => env('STORE_URL_MODE', 'path'),

    /*
    | Diseños disponibles. "assets" son las entradas de Vite que carga cada uno
    | (deben figurar también en vite.config.js).
    */
    'designs' => [
        'base' => [
            'label' => 'Plantilla base',
            'assets' => ['resources/css/storefront/base.css', 'resources/js/storefront/app.js'],
        ],
        'farruquito' => [
            'label' => 'Farruquito · On Stage, Artist Voice, Iconic',
            'assets' => ['resources/css/storefront/designs/farruquito.css', 'resources/js/storefront/designs/farruquito.js'],
        ],
    ],

];
