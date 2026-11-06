@php
    $row = 'display:flex;justify-content:space-between;gap:1rem;padding:.5rem 0;border-bottom:1px solid rgba(127,127,127,.15)';
@endphp

<x-filament-panels::page>
    @if ($failed)
        <div role="alert" style="padding:1rem 1.25rem;border-radius:.75rem;border:2px solid rgb(220 38 38);background:rgba(220,38,38,.08)">
            <strong>La última copia ha fallado.</strong> {{ $failed }}
        </div>
    @elseif ($enabled && $stale)
        <div role="alert" style="padding:1rem 1.25rem;border-radius:.75rem;border:2px solid rgb(217 119 6);background:rgba(217,119,6,.1)">
            <strong>{{ $last ? 'La última copia buena es del '.$last.'.' : 'Todavía no hay ninguna copia buena.' }}</strong>
            Haz una ahora y comprueba que la tarea programada (cron) del servidor está activa.
        </div>
    @endif
    @if ($offsiteMissing)
        <div role="alert" style="padding:1rem 1.25rem;border-radius:.75rem;border:2px solid rgb(217 119 6);background:rgba(217,119,6,.1)">
            <strong>La última copia solo está en el servidor.</strong> {{ $offsiteMissing }} Pulsa «Subir fuera» en la copia o «Probar Object Storage».
        </div>
    @endif

    <x-filament::section heading="Estado" description="Qué se guarda: la base de datos completa y los archivos subidos (fotos y diseños). No se guarda el código, que está en GitHub, ni las contraseñas del .env.">
        <dl>
            <div style="{{ $row }}"><dt>Última copia correcta</dt><dd><strong>{{ $last ?? 'Ninguna todavía' }}</strong></dd></div>
            <div style="{{ $row }}"><dt>Programación</dt><dd>{{ $schedule }}</dd></div>
            <div style="{{ $row }}"><dt>Dónde se guardan</dt><dd>{{ match (true) {
                $offsite => 'En el servidor y, además, fuera (Object Storage de OVH)',
                $disk === 's3' => 'Fuera del servidor (Object Storage)',
                default => 'En el servidor, carpeta privada (descárgalas de vez en cuando)',
            } }}</dd></div>
            <div style="{{ $row }}"><dt>Avisos por email a</dt><dd>{{ $email ?? 'Nadie (configura SUPERADMIN_EMAIL o un email en «Programación»)' }}</dd></div>
        </dl>
    </x-filament::section>

    {{ $this->table }}
</x-filament-panels::page>
