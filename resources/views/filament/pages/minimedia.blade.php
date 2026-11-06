@php
    $card = 'padding:1rem 1.25rem;border-radius:.75rem;border:1px solid rgba(127,127,127,.25)';
    $kpi = 'display:grid;gap:.15rem';
@endphp
<x-filament-panels::page>
    @if ($unsent > 0)
        <div role="alert" style="{{ $card }};border-color:rgb(220 38 38);background:rgba(220,38,38,.08)">
            <strong>{{ $unsent === 1 ? 'Un resumen no se ha podido enviar' : $unsent.' resúmenes no se han podido enviar' }}.</strong>
            Revisa <em>Ajustes → Correo</em> y pulsa «Enviar» en el mes. Se reintenta solo cada mañana.
        </div>
    @endif

    <div style="{{ $card }}">
        <div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap;align-items:baseline">
            <div style="font-weight:700">{{ $current['label'] }}, hasta hoy</div>
            <div style="opacity:.75;font-size:.875rem">{{ $rate }} · resumen a {{ $email }} el {{ $nextSend }}</div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;margin-top:1rem">
            <div style="{{ $kpi }}"><span style="opacity:.7;font-size:.8rem">Pedidos</span><strong style="font-size:1.4rem">{{ $current['orders'] }}</strong></div>
            <div style="{{ $kpi }}"><span style="opacity:.7;font-size:.8rem">Ventas sin IVA ni envío</span><strong style="font-size:1.4rem">{{ $money($current['sales_base']) }}</strong></div>
            <div style="{{ $kpi }}"><span style="opacity:.7;font-size:.8rem">Devoluciones del mes</span><strong style="font-size:1.4rem">− {{ $money($current['refunds_base']) }}</strong></div>
            <div style="{{ $kpi }}"><span style="opacity:.7;font-size:.8rem">Comisión Minimedia</span><strong style="font-size:1.4rem;color:rgb(217,119,6)">{{ $money($current['commission']) }}</strong></div>
        </div>
        @if ($current['stores'] !== [])
            <div style="overflow-x:auto;margin-top:1rem">
                <table style="width:100%;border-collapse:collapse;font-size:.875rem">
                    <thead><tr style="text-align:left;opacity:.7"><th style="padding:.4rem">Tienda</th><th style="padding:.4rem;text-align:right">Pedidos</th><th style="padding:.4rem;text-align:right">Neto sin IVA</th><th style="padding:.4rem;text-align:right">Comisión</th></tr></thead>
                    <tbody>
                        @foreach ($current['stores'] as $s)
                            <tr style="border-top:1px solid rgba(127,127,127,.15)">
                                <td style="padding:.4rem">{{ $s['store'] }}</td>
                                <td style="padding:.4rem;text-align:right">{{ $s['orders'] }}</td>
                                <td style="padding:.4rem;text-align:right">{{ $money($s['net_base']) }}</td>
                                <td style="padding:.4rem;text-align:right">{{ $money($s['commission']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <p style="opacity:.7;font-size:.8rem;margin:.75rem 0 0">Base: piezas sin IVA ni envío, con los cupones aplicados, de los pedidos cobrados en el mes, menos lo devuelto en el mes. Sin compras de prueba.</p>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
