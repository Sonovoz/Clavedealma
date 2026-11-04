@php
    $card = 'padding:1rem 1.25rem;border-radius:.75rem;border:1px solid rgba(127,127,127,.25)';
    $label = 'font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;opacity:.7';
    $value = 'font-size:1.5rem;font-weight:700;margin-top:.25rem';
@endphp
<x-filament-panels::page>
    <p style="margin:0;font-size:.875rem;opacity:.8">Este mes ({{ $monthName }}), por fecha de pago y sin compras de prueba. Los informes en PDF se descargan con los botones de arriba.</p>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(11rem,1fr));gap:1rem">
        <div style="{{ $card }}"><div style="{{ $label }}">Pedidos</div><div style="{{ $value }}">{{ $month['orders'] }}</div></div>
        <div style="{{ $card }}"><div style="{{ $label }}">Piezas</div><div style="{{ $value }}">{{ $month['units'] }}</div></div>
        <div style="{{ $card }}"><div style="{{ $label }}">Total cobrado</div><div style="{{ $value }}">{{ $month['gross'] }}</div></div>
        <div style="{{ $card }}"><div style="{{ $label }}">Devuelto</div><div style="{{ $value }}">{{ $month['refunds'] }}</div></div>
        <div style="{{ $card }}"><div style="{{ $label }}">Neto</div><div style="{{ $value }}">{{ $month['net'] }}</div></div>
    </div>

    @if ($month['stores'] !== [])
        <div style="{{ $card }}">
            <div style="{{ $label }};margin-bottom:.5rem">Por tienda</div>
            <table style="width:100%;font-size:.875rem;border-collapse:collapse">
                <thead><tr style="text-align:left;opacity:.7"><th style="padding:.4rem 0">Tienda</th><th style="text-align:right">Piezas</th><th style="text-align:right">Ventas</th><th style="text-align:right">Comisión estimada</th></tr></thead>
                <tbody>
                    @foreach ($month['stores'] as $s)
                        <tr style="border-top:1px solid rgba(127,127,127,.15)"><td style="padding:.4rem 0">{{ $s['store'] }}</td><td style="text-align:right">{{ $s['units'] }}</td><td style="text-align:right">{{ $s['sales'] }}</td><td style="text-align:right">{{ $s['commission'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>
