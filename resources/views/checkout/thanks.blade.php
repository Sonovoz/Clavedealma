@extends('checkout.layout', ['step' => $order->status === \App\Modules\Orders\Contracts\OrderStatus::Paid ? 4 : 3])

@if ($order->status === \App\Modules\Orders\Contracts\OrderStatus::PendingPayment)
    @push('head')
        {{-- Esperando la confirmación de la pasarela: la página se recarga sola. --}}
        <meta http-equiv="refresh" content="4">
    @endpush
@endif

@section('content')
    <div class="co-grid">
        <section class="co-card co-thanks">
            @switch($order->status)
                @case(\App\Modules\Orders\Contracts\OrderStatus::Paid)
                    <p class="co-kicker">Pedido confirmado</p>
                    <h1 class="co-h1">Gracias, {{ \Illuminate\Support\Str::before($order->customer_name, ' ') }}</h1>
                    <p>Hemos recibido el pago de tu pedido <strong>{{ $order->reference }}</strong>. Te hemos enviado la confirmación a <strong>{{ $order->email }}</strong>.</p>
                    <p>Cada pieza se fabrica para ti en {{ $order->lead_time_min_days }}–{{ $order->lead_time_max_days }} días laborables y el envío tarda {{ $order->delivery_min_days }}–{{ $order->delivery_max_days }} días más. Te avisaremos cuando salga.</p>
                    @if ($order->is_test)
                        <p class="co-fine co-test">Compra de prueba: no se ha cobrado dinero real.</p>
                    @endif
                    <a class="co-btn" href="{{ route('market') }}">Volver a Clave de Alma</a>
                    @break
                @case(\App\Modules\Orders\Contracts\OrderStatus::Cancelled)
                    <h1 class="co-h1">Pedido cancelado</h1>
                    <p>El pedido {{ $order->reference }} se canceló porque no se completó el pago. No se ha cobrado nada.</p>
                    <a class="co-btn" href="{{ route('cart.show') }}">Volver a la cesta</a>
                    @break
                @default
                    <h1 class="co-h1">Confirmando el pago…</h1>
                    <p>Estamos esperando la confirmación del pago de tu pedido {{ $order->reference }}. Esta página se actualiza sola.</p>
                    <a class="co-link" href="{{ route('checkout.pay', $order->token) }}">¿Algo ha fallado? Volver al pago</a>
            @endswitch
        </section>

        @include('checkout.partials.summary')
    </div>
@endsection
