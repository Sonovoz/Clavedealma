@extends('account.layout')

{{-- «Ver pedido» de WooCommerce: estado, seguimiento, facturas, devoluciones y detalles. --}}
@section('account')
    @php($placed = $order->created_at?->timezone('Europe/Madrid')->translatedFormat('j \d\e F \d\e Y'))
    <p>El pedido <mark class="co-mark">{{ $order->reference }}</mark> se realizó el <mark class="co-mark">{{ $placed }}</mark> y está actualmente <mark class="co-mark">{{ $order->status->wooLabel() }}</mark>.</p>

    @if ($order->status->isPaid())
        <div class="co-thanks">
            @include('checkout.partials.order-body')
        </div>
    @elseif ($order->status === \App\Modules\Orders\Contracts\OrderStatus::PendingPayment)
        <p>Este pedido está esperando el pago.</p>
        <a class="co-btn" href="{{ route('checkout.pay', $order->token) }}">Pagar</a>
    @endif

    <h2 class="co-h2">Detalles del pedido</h2>
    <table class="co-review__table">
        <thead><tr><th>Producto</th><th class="co-num">Total</th></tr></thead>
        <tbody>
            @foreach ($items as $item)
                <tr><td>{{ $item['name'] }} <strong>× {{ $item['quantity'] }}</strong><small>{{ $item['option'] }} · {{ $item['store'] }}</small></td><td class="co-num">{{ $item['total'] }}</td></tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr><th>Subtotal</th><td class="co-num">{{ $subtotal }}</td></tr>
            <tr><th>Envío <small>· {{ $order->shipping_zone->label() }}</small></th><td class="co-num">{{ $shippingPrice }}</td></tr>
            <tr><th>Método de pago</th><td class="co-num">{{ $paymentMethod }}</td></tr>
            <tr class="co-review__total"><th>Total</th><td class="co-num">{{ $total }}</td></tr>
        </tfoot>
    </table>
    <p class="co-fine">IVA incluido ({{ $vat }}).</p>

    @unless ($order->status->isPaid())
        <h2 class="co-h2">Dirección de envío</h2>
        <address class="co-address">{{ $order->customer_name }}<br>{{ $order->address_line1 }}@if ($order->address_line2), {{ $order->address_line2 }}@endif<br>{{ $order->postal_code }} {{ $order->city }}</address>
    @endunless

    <a class="co-link" href="{{ route('account.orders') }}">← Volver a Pedidos</a>
@endsection
