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
    | Tamaño máximo del ZIP de fotos en "Importar artículos" (MB). PHP también
    | debe permitirlo: upload_max_filesize y post_max_size (en Hostinger, hPanel → PHP).
    */
    'import_max_zip_kb' => (int) env('IMPORT_MAX_ZIP_MB', 200) * 1024,

];
