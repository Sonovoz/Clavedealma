{{-- Resumen de un pedido ya creado. Variables: $items, $subtotal, $shippingPrice, $total, $vat, $order. --}}
<aside class="co-card co-summary" aria-label="Resumen del pedido">
    <h2 class="co-h2">Pedido {{ $order->reference }}</h2>
    <ul class="co-mini">
        @foreach ($items as $item)
            <li>
                <span class="co-mini__img">@if ($item['image'])<img src="{{ $item['image'] }}" alt="" loading="lazy">@endif<b>{{ $item['quantity'] }}</b></span>
                <span class="co-mini__txt"><strong>{{ $item['name'] }}</strong><small>{{ $item['option'] }} · {{ $item['store'] }}</small></span>
                <span class="co-mini__price">{{ $item['total'] }}</span>
            </li>
        @endforeach
    </ul>
    <dl class="co-totals">
        <div><dt>Subtotal</dt><dd>{{ $subtotal }}</dd></div>
        <div><dt>Envío · {{ $order->shipping_zone->label() }}</dt><dd>{{ $shippingPrice }}</dd></div>
        <div class="co-totals__total"><dt>Total</dt><dd>{{ $total }}</dd></div>
    </dl>
    <p class="co-fine">IVA incluido ({{ $vat }}). Fabricación {{ $order->lead_time_min_days }}–{{ $order->lead_time_max_days }} días laborables + envío {{ $order->delivery_min_days }}–{{ $order->delivery_max_days }} días.</p>
</aside>
