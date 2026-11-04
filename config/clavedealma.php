<?php

return [

    /*
    | Dominio raíz. La matriz vive en este dominio y cada tienda en
    | <subdominio>.<domain>.
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

    'backups' => [
        'disk' => env('BACKUP_DISK', 'backups'),
        'timezone' => env('BACKUP_TIMEZONE', 'Europe/Madrid'),
    ],

];
