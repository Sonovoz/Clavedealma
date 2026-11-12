@extends('install.layout', ['step' => 4])
@section('content')
    <h1>¡Instalado!</h1>
    <p>Ya puedes entrar al panel con <strong>{{ $email }}</strong> y tu contraseña. Al entrar, te pedirá configurar la verificación en dos pasos con una app del móvil.</p>
    <p><a class="btn" href="{{ $url }}/admin" style="margin-top:4px">Entrar al panel</a></p>

    <h2 style="font-size:1.1rem;margin-top:28px">Un último paso: las tareas programadas</h2>
    <p>Las copias de seguridad, el resumen de Minimedia del día 1 y la anulación de pedidos sin pagar necesitan que algo despierte a la web <strong>cada minuto</strong>. Elige una de las dos formas:</p>
    <p><strong>A) Cron del servidor</strong> (lo más fiable). En el VPS, <code>crontab -e</code> y añade esta línea (cambia <code>php-web</code> si tu contenedor de PHP se llama de otra forma):</p>
    <pre>* * * * * docker exec php-web php {{ $root }}/artisan schedule:run >> /dev/null 2>&1</pre>
    <p><strong>B) Por una dirección secreta</strong>, desde un servicio de cron externo (por ejemplo cron-job.org), cada minuto:</p>
    <pre>{{ $url }}/cron/{{ $token }}</pre>
    <p class="muted">Guárdala bien: es la clave <code>CRON_TOKEN</code> del <code>.env</code>. En el panel, <em>Herramientas → Estado del sistema</em> dice cuándo se ejecutaron las tareas por última vez.</p>
    <p class="muted">Esta página no se vuelve a mostrar: el instalador ya no existe.</p>
@endsection
