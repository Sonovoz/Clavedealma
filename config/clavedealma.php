<?php

return [

    /*
    | Dominio raíz. La matriz vive en este dominio y cada tienda en <domain>/<clave>
    | (ADR 0013).
    */
    'domain' => env('APP_DOMAIN', 'clavedealma.test'),

    /*
    | Superadministrador inicial, creado por `php artisan db:seed`.
    */
    'superadmin' => [
        'name' => env('SUPERADMIN_NAME', 'Superadmin'),
        'email' => env('SUPERADMIN_EMAIL'),
        'password' => env('SUPERADMIN_PASSWORD'),
    ],

    /*
    | Copias de seguridad (Administración → Copias de seguridad). La frecuencia, la hora
    | y cuántas se conservan se eligen en el panel; aquí solo el disco donde se guardan.
    */
    /*
    | SEO (docs/seo/README.md). Fuera de producción NO se indexa nada (noindex y robots.txt
    | cerrado), para que Google no se quede con el servidor de pruebas. SEO_INDEXING
    | fuerza el valor si hace falta.
    */
    'seo' => [
        'indexing' => env('SEO_INDEXING'),
    ],

    /*
    | Compra (docs/compra/README.md). Los precios y el envío se calculan siempre en el
    | servidor: la cesta solo guarda variantes y cantidades.
    */
    'shop' => [
        'max_per_line' => 10,
        // Peso que se usa para el envío si la prenda base no tiene peso indicado.
        'default_weight_grams' => (int) env('SHOP_DEFAULT_WEIGHT_GRAMS', 400),
        // Destinos donde todavía no se vende: llevan otros impuestos (IGIC, IPSI) y aduana.
        'blocked_zones' => ['canarias', 'ceuta_melilla'],
        // Minutos que un pedido espera el pago antes de cancelarse solo.
        'pending_minutes' => 120,
        // Versión de las condiciones de venta que acepta el cliente (se guarda en el pedido).
        'terms_version' => env('SHOP_TERMS_VERSION', 'borrador-2026-11'),
    ],

    'orders' => [
        // Quién recibe el aviso de cada pedido pagado (por defecto, el superadministrador).
        'alert_email' => env('ORDERS_ALERT_EMAIL'),
    ],

    'backups' => [
        'disk' => env('BACKUP_DISK', 'backups'),
        'timezone' => env('BACKUP_TIMEZONE', 'Europe/Madrid'),
    ],

];
