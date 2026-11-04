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
    'backups' => [
        'disk' => env('BACKUP_DISK', 'backups'),
        'timezone' => env('BACKUP_TIMEZONE', 'Europe/Madrid'),
    ],

];
