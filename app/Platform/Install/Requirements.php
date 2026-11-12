<?php

namespace App\Platform\Install;

use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * Lo que necesita el servidor (ADR 0030), como el «Estado de salud» de WordPress.
 */
class Requirements
{
    private const EXTENSIONS = [
        'pdo_mysql' => 'conectar con MariaDB',
        'mbstring' => 'textos con acentos',
        'openssl' => 'cifrado y firma de Redsys',
        'intl' => 'fechas, precios e idioma del panel',
        'gd' => 'fotos y PDF',
        'zip' => 'copias de seguridad e importador',
        'fileinfo' => 'subida de archivos',
        'curl' => 'conexiones con Redsys, correo y copias',
        'dom' => 'PDF y hojas de cálculo',
        'xml' => 'PDF y hojas de cálculo',
        'ctype' => 'Laravel',
        'tokenizer' => 'Laravel',
    ];

    private const OPTIONAL = [
        'redis' => 'usar Redis para la caché y las sesiones (más rápido)',
        'bcmath' => 'cálculos con números muy grandes',
        'exif' => 'girar bien las fotos de móvil',
        'opcache' => 'que PHP vaya más rápido',
    ];

    public function __construct(private readonly EnvFile $env) {}

    /**
     * @return list<array{label: string, ok: bool, required: bool, hint: string}>
     */
    public function check(): array
    {
        $rows = [[
            'label' => 'PHP 8.3 o superior (ahora '.PHP_VERSION.')',
            'ok' => version_compare(PHP_VERSION, '8.3.0', '>='),
            'required' => true,
            'hint' => 'Cambia la versión de PHP del servidor.',
        ]];
        foreach (self::EXTENSIONS as $ext => $why) {
            $rows[] = ['label' => "Extensión {$ext} ({$why})", 'ok' => extension_loaded($ext), 'required' => true, 'hint' => "Instálala en el contenedor de PHP (p. ej. docker-php-ext-install {$ext})."];
        }
        foreach (self::OPTIONAL as $ext => $why) {
            $rows[] = ['label' => "Extensión {$ext}, opcional ({$why})", 'ok' => extension_loaded($ext), 'required' => false, 'hint' => 'No es imprescindible.'];
        }
        $rows[] = [
            'label' => 'Cifrado 3DES de OpenSSL (firma de Redsys)',
            'ok' => in_array('des-ede3-cbc', array_map('strtolower', openssl_get_cipher_methods()), true),
            'required' => true,
            'hint' => 'El OpenSSL del servidor no trae 3DES: sin él no se puede cobrar con Redsys.',
        ];
        $rows[] = ['label' => 'Se puede escribir el archivo .env', 'ok' => $this->env->writable(), 'required' => true, 'hint' => 'Da permiso de escritura al usuario del servidor web en la carpeta del proyecto.'];
        foreach (['storage', 'bootstrap/cache'] as $dir) {
            $rows[] = ['label' => "Se puede escribir en {$dir}/", 'ok' => is_writable(base_path($dir)), 'required' => true, 'hint' => "Da permiso de escritura en {$dir}/ (chmod -R 775 y el usuario del servidor web)."];
        }
        // Los del panel no van en GitHub (los publica composer): si faltan, se publican aquí.
        if (! is_file(public_path('js/filament/filament/app.js')) && is_writable(public_path())) {
            try {
                Artisan::call('filament:assets');
            } catch (Throwable) {
                // Se ve en la comprobación de abajo.
            }
        }
        $rows[] = [
            'label' => 'Estilos y JavaScript del panel (public/js/filament)',
            'ok' => is_file(public_path('js/filament/filament/app.js')),
            'required' => true,
            'hint' => 'Ejecuta php artisan filament:assets (o composer install) en el servidor, o da permiso de escritura en public/.',
        ];
        $rows[] = [
            'label' => 'Estilos de la tienda compilados (public/build)',
            'ok' => is_file(public_path('build/manifest.json')),
            'required' => true,
            'hint' => 'Sube el paquete ZIP de GitHub, que ya los trae, o ejecuta npm ci && npm run build.',
        ];

        return $rows;
    }

    public function passes(): bool
    {
        foreach ($this->check() as $row) {
            if ($row['required'] && ! $row['ok']) {
                return false;
            }
        }

        return true;
    }
}
