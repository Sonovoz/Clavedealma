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

    'returns' => [
        // Dirección a la que el cliente envía las piezas devueltas (va en las instrucciones).
        'address' => env('RETURNS_ADDRESS'),
    ],

    'artists' => [
        // Además de los superadministradores, quién recibe los avisos de los artistas (ADR 0023).
        'notice_email' => env('ARTIST_NOTICES_EMAIL'),
    ],

    'orders' => [
        // Quién recibe el aviso de cada pedido pagado (por defecto, el superadministrador).
        'alert_email' => env('ORDERS_ALERT_EMAIL'),
    ],

    'security' => [
        // Días que se conserva el registro de seguridad (guarda IPs: dato personal).
        'retention_days' => (int) env('SECURITY_LOG_DAYS', 365),
        // Verificación en dos pasos del panel, obligatoria para el equipo (ADR 0028).
        'two_factor_required' => (bool) env('TWO_FACTOR_REQUIRED', true),
    ],

    // Aviso por email de los errores de la web (App\Platform\Monitoring\ErrorAlerts).
    'errors' => [
        'alerts' => (bool) env('ERROR_ALERTS', false),
        'email' => env('ERROR_ALERT_EMAIL'),
    ],

    // Clave de la dirección /cron/<token> para lanzar las tareas programadas desde fuera (ADR 0030).
    'cron_token' => env('CRON_TOKEN', ''),

    // Comisión de Minimedia sobre las ventas de todas las tiendas (ADR 0029). Se cambia en el panel.
    'minimedia' => [
        'rate_bps' => (int) env('MINIMEDIA_RATE_BPS', 600),
        'email' => env('MINIMEDIA_EMAIL', 'info@minimedia.es'),
    ],

    'backups' => [
        'disk' => env('BACKUP_DISK', 'backups'),
        // Además, una copia fuera del servidor (disco «backups_offsite»: Object Storage de OVH).
        'offsite' => (bool) env('BACKUP_OFFSITE', false),
        'offsite_disk' => 'backups_offsite',
        'timezone' => env('BACKUP_TIMEZONE', 'Europe/Madrid'),
    ],

];
