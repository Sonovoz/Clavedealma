@extends('pdf.layout')
@section('title', 'Comisión Minimedia · '.$label)
@section('footer', 'Resumen informativo de la comisión de Minimedia: no es una factura')

@section('content')
    <h1>Comisión Minimedia · {{ $label }}</h1>
    <p class="muted">Ventas de todas las tiendas, por fecha de pago. Base: piezas sin IVA ni envío, con los cupones aplicados, menos lo devuelto en el mes. Sin compras de prueba.</p>

    <table class="kpis"><tr>
        <td><span class="muted small">Pedidos</span><br><span class="v">{{ $orders }}</span></td>
        <td><span class="muted small">Neto sin IVA</span><br><span class="v">{{ $net }}</span></td>
        <td><span class="muted small">Porcentaje</span><br><span class="v">{{ $rate }}</span></td>
        <td><span class="muted small">Comisión Minimedia</span><br><span class="v">{{ $commission }}</span></td>
    </tr></table>

    <h2>Resumen</h2>
    <table>
        <tbody>
            <tr><td>Ventas sin IVA ni envío</td><td class="num">{{ $sales }}</td></tr>
            <tr><td>Devoluciones del mes (piezas, sin IVA)</td><td class="num">− {{ $refunds }}</td></tr>
            <tr><td><strong>Neto</strong></td><td class="num"><strong>{{ $net }}</strong></td></tr>
            <tr><td><strong>Comisión Minimedia ({{ $rate }})</strong></td><td class="num"><strong>{{ $commission }}</strong></td></tr>
        </tbody>
    </table>

    <h2>Por tienda</h2>
    @if ($stores === [])<p class="muted">Sin ventas en el mes.</p>@else
    <table>
        <thead><tr><th>Tienda</th><th class="num">Pedidos</th><th class="num">Ventas</th><th class="num">Devoluciones</th><th class="num">Neto</th><th class="num">Comisión</th></tr></thead>
        <tbody>@foreach ($stores as $s)<tr><td>{{ $s['store'] }}</td><td class="num">{{ $s['orders'] }}</td><td class="num">{{ $s['sales'] }}</td><td class="num">− {{ $s['refunds'] }}</td><td class="num">{{ $s['net'] }}</td><td class="num">{{ $s['commission'] }}</td></tr>@endforeach</tbody>
    </table>
    <p class="small muted">La comisión total se calcula sobre el neto total; por redondeo puede diferir en un céntimo de la suma de las tiendas.</p>@endif
@endsection
