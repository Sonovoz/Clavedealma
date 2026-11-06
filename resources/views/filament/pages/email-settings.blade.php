@php
    $row = 'display:flex;justify-content:space-between;gap:1rem;padding:.55rem 0;border-bottom:1px solid rgba(127,127,127,.15);flex-wrap:wrap';
    $card = 'padding:1rem 1.25rem;border-radius:.75rem;border:1px solid rgba(127,127,127,.25)';
    $ok = 'color:rgb(22,163,74)';
    $warn = 'color:rgb(217,119,6)';
@endphp
<x-filament-panels::page>
    @if (! $smtp)
        <div role="alert" style="{{ $card }};border-color:rgb(245 158 11);background:rgba(245,158,11,.1)">
            <strong>En este servidor los emails no salen por SMTP</strong> (ahora: «{{ $mailer }}»). En el <code>.env</code> debe poner <code>MAIL_MAILER=smtp</code>. En tu ordenador es normal: ahí se guardan en el registro.
        </div>
    @endif

    <div style="{{ $card }}">
        <div style="font-weight:700;margin-bottom:.5rem">Correo de salida</div>
        <div style="{{ $row }}"><span>Protocolo</span><strong style="{{ $smtp ? $ok : $warn }}">{{ $smtp ? 'SMTP' : $mailer }}</strong></div>
        <div style="{{ $row }}"><span>Servidor</span><strong style="{{ $source ? '' : $warn }}">{{ $source ? $host.':'.$port : 'Sin configurar' }}{{ $source ? ' · '.($source === 'panel' ? 'escrito en el panel' : 'del .env') : '' }}</strong></div>
        <div style="{{ $row }}"><span>Cifrado</span><strong>{{ $encryption }}</strong></div>
        <div style="{{ $row }}"><span>Usuario</span><strong>{{ $username ?: '—' }}</strong></div>
        <div style="{{ $row }}"><span>Contraseña</span><strong style="{{ $hasPassword ? $ok : $warn }}">{{ $hasPassword ? 'Guardada' : 'Falta' }}</strong></div>
        <div style="{{ $row }};border:0"><span>Remitente</span><strong>{{ $from }}</strong></div>
    </div>

    <div style="{{ $card }}">
        <div style="font-weight:700;margin-bottom:.5rem">Qué sale por aquí</div>
        <p style="margin:0;opacity:.85">Todos los emails de la web: confirmación de pedido, envío y seguimiento, notas para el cliente, facturas, devoluciones, «Mi cuenta», avisos de artistas, nuevo pedido al equipo, copias de seguridad, avisos de errores y el resumen mensual de Minimedia.</p>
    </div>
</x-filament-panels::page>
