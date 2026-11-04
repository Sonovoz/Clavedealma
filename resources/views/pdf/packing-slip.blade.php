@extends('pdf.layout')
@section('title', 'Albarán '.$order->reference)
@section('footer', 'Albarán de envío · '.$order->reference)

@section('content')
    <table class="grid">
        <tr>
            <td style="width:55%">
                <h1>Albarán de envío</h1>
                <p>Pedido <strong>{{ $order->reference }}</strong><br>Fecha {{ $date }}<br>Transporte: {{ $order->tracking_carrier ?? $order->shipping_carrier }}@if ($order->tracking_number) · {{ $order->tracking_number }}@endif</p>
            </td>
            <td style="width:45%">
                <p class="small muted" style="margin:0">ENTREGAR A</p>
                <p style="font-size:11pt;margin:1mm 0 0"><strong>{{ $order->customer_name }}</strong><br>
                    {{ $order->address_line1 }}@if ($order->address_line2)<br>{{ $order->address_line2 }}@endif<br>
                    {{ $order->postal_code }} {{ $order->city }}@if ($order->province) ({{ $order->province }})@endif<br>
                    {{ $country }}<br>Tel. {{ $order->phone }}</p>
            </td>
        </tr>
    </table>

    <h2>Contenido del paquete · {{ $units }} {{ $units === 1 ? 'pieza' : 'piezas' }}</h2>
    <table>
        <thead><tr><th class="check">OK</th><th>SKU</th><th>Pieza</th><th>Talla · color</th><th>Tienda</th><th class="num">Ud.</th></tr></thead>
        <tbody>
            @foreach ($lines as $l)
                <tr><td class="check"><span class="box"></span></td><td>{{ $l['sku'] }}</td><td>{{ $l['name'] }}</td><td>{{ $l['option'] }}</td><td>{{ $l['store'] }}</td><td class="num"><strong>{{ $l['quantity'] }}</strong></td></tr>
            @endforeach
        </tbody>
    </table>
    @if ($order->customer_note)<p class="small"><strong>Nota del cliente:</strong> {{ $order->customer_note }}</p>@endif

    <h2>Gracias por tu compra</h2>
    <p>Cada pieza se ha fabricado para ti. Si algo no te convence, tienes 14 días desde la entrega para devolver las piezas no personalizadas: entra en la página de tu pedido (el enlace «Ver mi pedido» de tus emails) y pulsa «Devolver piezas».</p>
@endsection
