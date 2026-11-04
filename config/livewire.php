<?php

/*
| Solo se cambia la subida temporal de ficheros (el resto usa los valores de Livewire):
| el ZIP de fotos de "Importar artículos" supera los 12 MB por defecto.
*/
return [

    'temporary_file_upload' => [
        'disk' => env('LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK'),
        'rules' => ['required', 'file', 'max:'.((int) env('IMPORT_MAX_ZIP_MB', 200) * 1024)],
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        'max_upload_time' => 15,
        'cleanup' => true,
    ],

];
