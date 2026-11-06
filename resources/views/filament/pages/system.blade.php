@php
    $row = 'display:flex;justify-content:space-between;gap:1rem;padding:.5rem 0;border-bottom:1px solid rgba(127,127,127,.15)';
    $ok = fn (bool $v, string $yes = 'Correcto', string $no = 'Revisar') => $v
        ? '<span style="color:rgb(22,163,74);font-weight:600">'.$yes.'</span>'
        : '<span style="color:rgb(220,38,38);font-weight:600">'.$no.'</span>';
@endphp

<x-filament-panels::page>
    <x-filament::section heading="Instalación" description="Versión desplegada y entorno.">
        <dl>
            <div style="{{ $row }}"><dt>Versión</dt><dd><code>{{ $version }}</code></dd></div>
            <div style="{{ $row }}"><dt>Entorno</dt><dd>{{ $environment }}</dd></div>
            <div style="{{ $row }}"><dt>PHP</dt><dd>{{ $phpVersion }}</dd></div>
            <div style="{{ $row }}"><dt>Laravel</dt><dd>{{ $laravelVersion }}</dd></div>
            <div style="{{ $row }}"><dt>Modo mantenimiento</dt><dd>{!! $maintenance ? '<span style="color:rgb(217,119,6);font-weight:600">Activado</span>' : 'Desactivado' !!}</dd></div>
        </dl>
    </x-filament::section>

    <x-filament::section heading="Salud" description="Comprobaciones básicas del servidor.">
        <dl>
            <div style="{{ $row }}"><dt>Conexión con la base de datos</dt><dd>{!! $ok($databaseOk) !!}</dd></div>
            <div style="{{ $row }}"><dt>Migraciones pendientes</dt><dd>{!! $pendingMigrations === 0 ? $ok(true, 'Ninguna') : $ok(false, '', (string) $pendingMigrations) !!}</dd></div>
            <div style="{{ $row }}"><dt>Carpetas con permiso de escritura</dt><dd>{!! $ok($storageWritable) !!}</dd></div>
            <div style="{{ $row }}"><dt>Enlace de archivos públicos (storage)</dt><dd>{!! $ok($storageLinked, 'Creado', 'Falta') !!}</dd></div>
            <div style="{{ $row }}"><dt>Avisos de errores por email</dt><dd>{!! $errorAlerts ? $ok(true, 'Activados, a '.e($errorEmail)) : $ok(false, '', 'Desactivados (ERROR_ALERTS en el .env)') !!}</dd></div>
        </dl>
    </x-filament::section>

    <x-filament::section heading="Servicios configurados" description="Drivers activos en este entorno (.env).">
        <dl>
            @foreach ($drivers as $label => $driver)
                <div style="{{ $row }}"><dt>{{ $label }}</dt><dd><code>{{ $driver }}</code></dd></div>
            @endforeach
        </dl>
    </x-filament::section>

    @if ($lastUpdate !== [])
        <x-filament::section heading="Resultado de la última actualización">
            <dl>
                @foreach ($lastUpdate as $result)
                    <div style="{{ $row }}">
                        <dt>{{ $result['step'] }}</dt>
                        <dd style="text-align:right">{!! $ok($result['ok'], 'Hecho', 'Error') !!}<br><small style="opacity:.7">{{ \Illuminate\Support\Str::limit($result['output'], 160) }}</small></dd>
                    </div>
                @endforeach
            </dl>
        </x-filament::section>
    @endif
</x-filament-panels::page>
