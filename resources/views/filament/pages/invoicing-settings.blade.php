@php
    $row = 'display:flex;justify-content:space-between;gap:1rem;padding:.5rem 0;border-bottom:1px solid rgba(127,127,127,.15)';
    $card = 'padding:1rem 1.25rem;border-radius:.75rem;border:1px solid rgba(127,127,127,.25)';
@endphp
<x-filament-panels::page>
    @if ($missing !== [])
        <div role="alert" style="{{ $card }};border-color:rgb(245 158 11);background:rgba(245,158,11,.1)">
            <strong>No se están emitiendo facturas.</strong> Faltan: {{ implode(', ', $missing) }}. Pulsa «Editar datos fiscales». Los pedidos cobrados mientras tanto quedan pendientes y se facturan con «Emitir facturas pendientes».
        </div>
    @endif

    <div style="{{ $card }}">
        <div style="{{ $row }}"><span>Razón social</span><strong>{{ $issuer->legal_name ?? '—' }}</strong></div>
        <div style="{{ $row }}"><span>NIF</span><strong>{{ $issuer->nif ?? '—' }}</strong></div>
        <div style="{{ $row }}"><span>Domicilio fiscal</span><strong>{{ trim(($issuer->address ?? '').' · '.($issuer->postal_code ?? '').' '.($issuer->city ?? ''), ' ·') ?: '—' }}</strong></div>
        <div style="{{ $row }}"><span>Email de facturación</span><strong>{{ $issuer->email ?? '—' }}</strong></div>
        <div style="{{ $row }}"><span>Series</span><strong>{{ $issuer->simplified_series }} (simplificadas) · {{ $issuer->rectifying_series }} (rectificativas)</strong></div>
        <div style="{{ $row }};border:0"><span>Facturas emitidas</span><strong>{{ $count }}</strong></div>
    </div>

    <div style="{{ $card }}">
        <div style="font-weight:700;margin-bottom:.5rem">Veri*Factu</div>
        <div style="{{ $row }}"><span>Estado</span>
            <strong style="color:{{ $verifactuEnabled ? 'rgb(22,163,74)' : 'rgb(217,119,6)' }}">{{ $verifactuEnabled ? 'Activado: QR, leyenda y envío a la AEAT en las facturas nuevas' : 'Desactivado hasta que sea obligatorio (VERIFACTU_ENABLED)' }}</strong></div>
        <div style="{{ $row }}"><span>Huella encadenada</span><strong>Se guarda en todas las facturas, para activarlo sin huecos</strong></div>
        <div style="{{ $row }}"><span>QR de cotejo</span><strong>{{ $verifactuEnabled ? ($production ? 'Sede de la AEAT (producción)' : 'Entorno de pruebas de la AEAT') : 'No se imprime' }}</strong></div>
        <div style="{{ $row }};border:0"><span>Envío de los registros a la AEAT</span>
            <strong style="color:{{ $aeatConfigured ? 'rgb(22,163,74)' : 'rgb(217,119,6)' }}">{{ $aeatConfigured ? 'Configurado' : 'Sin configurar: falta el certificado digital de Clave de Alma' }}</strong></div>
    </div>
</x-filament-panels::page>
