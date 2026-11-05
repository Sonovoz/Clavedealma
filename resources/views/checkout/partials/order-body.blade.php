{{-- Cuerpo de la página de un pedido pagado: seguimiento, facturas, devoluciones y dirección.
     Lo usan «Pedido recibido» y «Mi cuenta → Ver pedido». Variables: OrderPage::data(). --}}
@php
    use App\Modules\Orders\Contracts\OrderStatus;
    $steps = [
        ['label' => 'Pago recibido', 'at' => $order->paid_at],
        ['label' => 'Fabricando tus piezas', 'at' => $order->production_started_at],
        ['label' => 'Preparando el envío', 'at' => $order->ready_at],
        ['label' => 'En camino', 'at' => $order->shipped_at],
        ['label' => 'Entregado', 'at' => $order->delivered_at],
    ];
@endphp
<ol class="co-track" aria-label="Estado del pedido">
    @foreach ($steps as $s)
        <li @class(['is-done' => $s['at'] !== null])>
            <span class="co-track__dot" aria-hidden="true"></span>
            <span class="co-track__label">{{ $s['label'] }}</span>
            <span class="co-track__date">{{ $s['at']?->timezone('Europe/Madrid')->format('d/m/Y') }}</span>
        </li>
    @endforeach
</ol>

@if ($order->tracking_number)
    <p>Tu pedido va con <strong>{{ $order->tracking_carrier }}</strong>. Número de seguimiento: <strong>{{ $order->tracking_number }}</strong>.</p>
    @if ($order->tracking_url)
        <a class="co-btn" href="{{ $order->tracking_url }}" target="_blank" rel="noopener">Seguir el envío</a>
    @endif
@elseif ($order->status !== OrderStatus::Delivered)
    <p>Cada pieza se fabrica para ti en {{ $order->lead_time_min_days }}–{{ $order->lead_time_max_days }} días laborables y el envío tarda {{ $order->delivery_min_days }}–{{ $order->delivery_max_days }} días más. Te avisaremos por email cuando salga.</p>
@endif
@if (session('return.created'))
    <p class="co-ok" role="status">Hemos recibido tu solicitud de devolución {{ session('return.created') }}. Te escribiremos por email con los pasos.</p>
@endif
@if (session('return.error'))
    <p class="co-alert" role="alert">{{ session('return.error') }}</p>
@endif
@if ($invoices !== [])
    <h2 class="co-h2">Facturas</h2>
    <ul class="co-returns">
        @foreach ($invoices as $inv)
            <li><span><a href="{{ $inv['url'] }}">{{ $inv['label'] }} {{ $inv['code'] }}</a></span><span>{{ $inv['total'] }}</span></li>
        @endforeach
    </ul>
@endif
@if ($returns !== [])
    <h2 class="co-h2">Devoluciones</h2>
    <ul class="co-returns">
        @foreach ($returns as $r)
            <li><span><strong>{{ $r['reference'] }}</strong> · {{ $r['status'] }}</span><span>{{ $r['amount'] }}</span></li>
        @endforeach
    </ul>
@endif
<h2 class="co-h2">Dirección de envío</h2>
<address class="co-address">
    {{ $order->customer_name }}<br>{{ $order->address_line1 }}@if ($order->address_line2), {{ $order->address_line2 }}@endif<br>
    {{ $order->postal_code }} {{ $order->city }}@if ($order->province) ({{ $order->province }})@endif · {{ \App\Storefront\Checkout\Countries::ALL[$order->country] ?? $order->country }}<br>
    {{ $order->phone }}
</address>
@if ($order->customer_note)
    <p class="co-fine">Notas del pedido: {{ $order->customer_note }}</p>
@endif
@if ($canReturn)
    <p class="co-fine">¿Algo no te convence? Puedes devolver las piezas no personalizadas hasta el {{ $returnDeadline }}.</p>
    <a class="co-btn co-btn--ghost" href="{{ route('returns.create', $order->token) }}">Devolver piezas</a>
@endif
@if ($order->is_test)
    <p class="co-fine co-test">Compra de prueba: no se ha cobrado dinero real.</p>
@endif
