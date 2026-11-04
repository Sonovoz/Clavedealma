<?php

/*
| Tiendas públicas (escaparate). Cada tienda tiene un DISEÑO propio (portada,
| secciones y ambiente, hechos a medida en resources/views/storefront/designs/<clave>)
| y una PASARELA de productos común que muestra los artículos asignados a la tienda.
| Esquema completo: docs/tiendas/README.md.
*/
return [

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
