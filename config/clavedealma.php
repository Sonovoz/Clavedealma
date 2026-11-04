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

];
