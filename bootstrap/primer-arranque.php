<?php

/*
| Primer arranque, antes de cargar Laravel (lo incluye public/index.php). ADR 0030.
|
| 1. Si faltan las dependencias (vendor/), explica cómo instalarlas en lugar de dar un error.
| 2. Si no hay .env, lo crea a partir de .env.servidor.example con una APP_KEY nueva, para
|    que el instalador web (/instalar) pueda arrancar y pedir la base de datos.
| 3. Mientras no esté instalada (sin storage/app/instalado), caché y sesiones van en archivos:
|    todavía no hay tablas. Se fija antes de leer el .env, así que manda sobre él.
*/

$root = dirname(__DIR__);

$page = function (string $title, string $body): never {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        .'<title>'.$title.' · Clave de Alma</title><body style="margin:0;background:#070707;color:#e8e4de;font:16px/1.6 system-ui,sans-serif">'
        .'<main style="max-width:640px;margin:10vh auto;padding:0 16px"><h1 style="font-size:1.6rem">'.$title.'</h1>'.$body.'</main></body></html>';
    exit;
};

if (! is_file($root.'/vendor/autoload.php')) {
    $page('Faltan las dependencias', '<p>La web está subida, pero falta la carpeta <code>vendor/</code> con las librerías de PHP.</p>'
        .'<p>Hay dos formas de tenerla:</p><ol>'
        .'<li>Subir el <strong>paquete ZIP</strong> de GitHub (Actions → «Paquete para el servidor»), que ya la lleva.</li>'
        .'<li>O, en el servidor, dentro de la carpeta del proyecto: <code>composer install --no-dev --optimize-autoloader</code> y <code>npm ci &amp;&amp; npm run build</code>.</li>'
        .'</ol><p>Guía: <code>docs/instalacion-servidor.md</code>.</p>');
}

if (! is_file($root.'/.env') && is_file($root.'/.env.servidor.example')) {
    $env = (string) file_get_contents($root.'/.env.servidor.example');

    $https = ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' || (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $host = (string) preg_replace('/[^A-Za-z0-9.\-:]/', '', (string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost'));
    $domain = (string) preg_replace('/:\d+$/', '', $host);

    $set = function (string $key, string $value) use (&$env): void {
        $env = (string) preg_replace('/^'.preg_quote($key, '/').'=.*$/m', $key.'='.$value, $env);
    };
    $set('APP_KEY', 'base64:'.base64_encode(random_bytes(32)));
    $set('APP_URL', ($https ? 'https' : 'http').'://'.$host);
    $set('APP_DOMAIN', preg_replace('/^www\./', '', $domain) ?? $domain);
    $set('SESSION_SECURE_COOKIE', $https ? 'true' : 'false');

    if (@file_put_contents($root.'/.env', $env) === false) {
        $page('No se puede crear la configuración', '<p>La web necesita crear su archivo <code>.env</code> en la carpeta del proyecto, pero no tiene permiso de escritura.</p>'
            .'<p>Da permisos de escritura al usuario del servidor web (por ejemplo <code>www-data</code>) en la carpeta del proyecto, en <code>storage/</code> y en <code>bootstrap/cache/</code>, y recarga la página.</p>');
    }
    @chmod($root.'/.env', 0600);
}

if (! is_file($root.'/storage/app/instalado')) {
    foreach (['CACHE_STORE' => 'file', 'SESSION_DRIVER' => 'file'] as $key => $value) {
        putenv("{$key}={$value}");
        $_ENV[$key] = $_SERVER[$key] = $value;
    }
}
