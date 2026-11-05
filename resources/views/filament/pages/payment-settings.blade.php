@php
    $row = 'display:flex;justify-content:space-between;gap:1rem;padding:.55rem 0;border-bottom:1px solid rgba(127,127,127,.15);flex-wrap:wrap';
    $card = 'padding:1rem 1.25rem;border-radius:.75rem;border:1px solid rgba(127,127,127,.25)';
    $ok = 'color:rgb(22,163,74)';
    $warn = 'color:rgb(217,119,6)';
    $from = fn (?string $s): string => $s === null ? 'falta' : ($s === 'panel' ? 'escrito en el panel' : 'del .env del servidor');
@endphp
<x-filament-panels::page>
    {{-- Como «WooCommerce → Ajustes → Pagos» con el plugin de Redsys (ADR 0026 y 0027). --}}
    @if (! $active)
        <div role="alert" style="{{ $card }};border-color:rgb(245 158 11);background:rgba(245,158,11,.1)">
            <strong>Redsys no es la pasarela activa en este servidor</strong> (ahora: «{{ $driver }}»). En el <code>.env</code> del servidor debe poner <code>PAYMENT_DRIVER=redsys</code>. Puedes dejar los datos preparados aquí igualmente.
        </div>
    @endif
    @if ($live)
        <div role="status" style="{{ $card }};border-color:rgb(220 38 38);background:rgba(220,38,38,.08)">
            <strong>Cobros reales.</strong> Los pagos se cobran de verdad en el TPV del banco.
        </div>
    @endif

    <div style="{{ $card }}">
        <div style="font-weight:700;margin-bottom:.5rem">Estado del TPV</div>
        <div style="{{ $row }}"><span>Entorno</span><strong style="{{ $live ? $ok : $warn }}">{{ $live ? 'Real (sis.redsys.es): se cobra de verdad' : 'Pruebas (sis-t.redsys.es): no se cobra nada' }}</strong></div>
        <div style="{{ $row }}"><span>Código de comercio (FUC)</span>
            <strong style="{{ $sources['merchant_code'] ? $ok : $warn }}">{{ $config['merchant_code'] ?: '—' }} · {{ $from($sources['merchant_code']) }}@if ($testMerchant) · comercio público de pruebas @endif</strong></div>
        <div style="{{ $row }}"><span>Terminal</span><strong>{{ $config['terminal'] ?: '—' }}</strong></div>
        <div style="{{ $row }}"><span>Clave secreta SHA-256</span>
            <strong style="{{ $configured ? $ok : $warn }}">{{ $configured ? 'Guardada y válida' : 'Falta o no es válida' }} · {{ $from($sources['secret']) }}</strong></div>
        <div style="{{ $row }}"><span>Nombre en la página de pago</span><strong>{{ $config['merchant_name'] ?? 'Clave de Alma' }}</strong></div>
        <div style="{{ $row }}"><span>Métodos de pago</span>
            <strong>{{ ($config['card'] ?? true) ? 'Tarjeta de crédito o débito ✓' : 'Tarjeta ✗' }} · {{ ($config['bizum'] ?? false) ? 'Bizum ✓' : 'Bizum ✗ (sin activar)' }}</strong></div>
        <div style="{{ $row }};border:0"><span>Último aviso del banco</span>
            <strong style="{{ $lastNotice ? $ok : $warn }}">{{ $lastNotice ?? 'Todavía ninguno: la URL de notificación aún no se ha usado' }}</strong></div>
    </div>

    <div style="{{ $card }}">
        <div style="font-weight:700;margin-bottom:.5rem">Datos para el banco</div>
        <div style="{{ $row }}"><span>URL de notificación (avisos de cada cobro)</span>
            <strong style="display:flex;gap:.5rem;align-items:center"><code>{{ $notifyUrl }}</code>
                <x-filament::icon-button icon="heroicon-o-clipboard-document" label="Copiar" color="gray" size="sm"
                    data-copy="{{ $notifyUrl }}" x-on:click="navigator.clipboard.writeText($el.dataset.copy)" /></strong></div>
        <div style="{{ $row }}"><span>Tipo de notificación</span><strong>HTTP (en línea)</strong></div>
        <div style="{{ $row }}"><span>Parámetros en las URLs</span><strong>Sí</strong></div>
        <div style="{{ $row }}"><span>URL de vuelta (OK y KO)</span><strong>No hace falta configurarlas: se envían solas en cada pago</strong></div>
        <div style="{{ $row }};border:0"><span>Firma</span><strong>HMAC SHA-256 (la de siempre de Redsys)</strong></div>
    </div>

    <div style="{{ $card }}">
        <div style="font-weight:700;margin-bottom:.75rem">Pasos para ponerlo en marcha</div>
        <ol style="margin:0;padding:0;list-style:none;display:grid;gap:.75rem">
            @foreach ($steps as $i => $step)
                <li style="display:flex;gap:.75rem;align-items:flex-start">
                    <span style="flex:none;width:1.6rem;height:1.6rem;border-radius:999px;display:grid;place-items:center;font-size:.8rem;font-weight:700;{{ $step['done'] ? 'background:rgb(22,163,74);color:#fff' : 'border:1px solid rgba(127,127,127,.5)' }}">{{ $step['done'] ? '✓' : $i + 1 }}</span>
                    <span><strong>{{ $step['title'] }}</strong><br><span style="opacity:.8">{{ $step['text'] }}</span></span>
                </li>
            @endforeach
        </ol>
    </div>

    <div style="{{ $card }}">
        <div style="font-weight:700;margin-bottom:.5rem">Últimos cobros con Redsys</div>
        @if ($payments === [])
            <p style="opacity:.7;margin:0">Aún no hay ninguno.</p>
        @else
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse;font-size:.875rem">
                    <thead><tr style="text-align:left;opacity:.7"><th style="padding:.4rem">Fecha</th><th style="padding:.4rem">Pedido</th><th style="padding:.4rem">Nº Redsys</th><th style="padding:.4rem">Método</th><th style="padding:.4rem;text-align:right">Importe</th><th style="padding:.4rem">Estado</th></tr></thead>
                    <tbody>
                        @foreach ($payments as $p)
                            <tr style="border-top:1px solid rgba(127,127,127,.15)">
                                <td style="padding:.4rem">{{ $p['date'] }}</td>
                                <td style="padding:.4rem">{{ $p['order'] }}</td>
                                <td style="padding:.4rem;font-family:monospace">{{ $p['number'] }}</td>
                                <td style="padding:.4rem">{{ $p['method'] }}</td>
                                <td style="padding:.4rem;text-align:right">{{ $p['amount'] }}</td>
                                <td style="padding:.4rem;{{ $p['ok'] ? $ok : '' }}">{{ $p['status'] }}@if ($p['reason']) · <span style="opacity:.75">{{ $p['reason'] }}</span>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p style="opacity:.7;margin:.5rem 0 0;font-size:.8rem">«Pendiente» es normal: cada visita a la página de Redsys es un número nuevo y solo se cobra el que el cliente paga.</p>
        @endif
    </div>
</x-filament-panels::page>
