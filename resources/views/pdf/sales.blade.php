@extends('pdf.layout')
@section('title', 'Informe de ventas '.$from.' – '.$to)
@section('footer', 'Informe de ventas informativo: no sustituye a las facturas ni a las liquidaciones')

@section('content')
    <h1>Informe de ventas</h1>
    <p class="muted">Del {{ $from }} al {{ $to }}, por fecha de pago. {{ $includeTests ? 'Incluye compras de prueba.' : 'Sin compras de prueba.' }}</p>

    <table class="kpis"><tr>
        <td><span class="muted small">Pedidos</span><br><span class="v">{{ $orders }}</span></td>
        <td><span class="muted small">Piezas</span><br><span class="v">{{ $units }}</span></td>
        <td><span class="muted small">Ventas (IVA incl.)</span><br><span class="v">{{ $gross }}</span></td>
        <td><span class="muted small">Ticket medio</span><br><span class="v">{{ $average }}</span></td>
    </tr></table>

    <h2>Resumen</h2>
    <table>
        <tbody>
            <tr><td>Piezas vendidas (IVA incluido)</td><td class="num">{{ $goods }}</td></tr>
            <tr><td>Envíos cobrados (IVA incluido)</td><td class="num">{{ $shipping }}</td></tr>
            <tr><td><strong>Total cobrado</strong></td><td class="num"><strong>{{ $gross }}</strong></td></tr>
            <tr><td class="muted">de los que IVA</td><td class="num muted">{{ $vat }}</td></tr>
            <tr><td>Dinero devuelto en el periodo (cancelaciones y devoluciones)</td><td class="num">− {{ $refunds }}</td></tr>
            <tr><td><strong>Neto</strong></td><td class="num"><strong>{{ $net }}</strong></td></tr>
        </tbody>
    </table>

    <h2>Por tienda</h2>
    @if ($stores === [])<p class="muted">Sin ventas en el periodo.</p>@else
    <table>
        <thead><tr><th>Tienda</th><th class="num">Piezas</th><th class="num">Ventas IVA incl.</th><th class="num">Base sin IVA</th><th class="num">Comisión estimada</th></tr></thead>
        <tbody>@foreach ($stores as $s)<tr><td>{{ $s['store'] }}</td><td class="num">{{ $s['units'] }}</td><td class="num">{{ $s['sales'] }}</td><td class="num">{{ $s['base'] }}</td><td class="num">{{ $s['commission'] }}</td></tr>@endforeach</tbody>
    </table>
    <p class="small muted">Comisión estimada con el porcentaje de cada pieza en el momento de la venta, sobre el precio sin IVA y sin envío, antes de devoluciones. La liquidación definitiva al artista llegará con la fase 6.</p>@endif

    <h2>Piezas más vendidas</h2>
    @if ($products === [])<p class="muted">—</p>@else
    <table>
        <thead><tr><th>SKU</th><th>Pieza</th><th>Tienda</th><th class="num">Ud.</th><th class="num">Ventas</th></tr></thead>
        <tbody>@foreach ($products as $p)<tr><td>{{ $p['sku'] }}</td><td>{{ $p['name'] }} <span class="muted">{{ $p['option'] }}</span></td><td>{{ $p['store'] }}</td><td class="num">{{ $p['units'] }}</td><td class="num">{{ $p['sales'] }}</td></tr>@endforeach</tbody>
    </table>@endif

    @if ($byStatus !== [])
        <h2>Estado actual de esos pedidos</h2>
        <table><tbody>@foreach ($byStatus as $s)<tr><td>{{ $s['label'] }}</td><td class="num">{{ $s['count'] }}</td></tr>@endforeach</tbody></table>
    @endif
@endsection
