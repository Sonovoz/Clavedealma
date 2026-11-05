@php
    use App\Modules\Orders\Contracts\OrderStatus;
    $paid = $order->status->isPaid();
@endphp
{{-- «Pedido recibido» (como WooCommerce) y, después, la página del pedido del cliente. --}}
@extends('checkout.layout', ['step' => $paid ? 3 : 2])

@if ($order->status === OrderStatus::PendingPayment)
    @push('head')
        {{-- Esperando la confirmación de la pasarela: la página se recarga sola. --}}
        <meta http-equiv="refresh" content="4">
    @endpush
@endif

@section('content')
    <div class="co-grid">
        <section class="co-card co-thanks">
            @if ($paid)
                <p class="co-kicker">{{ $order->status->customerLabel() }}</p>
                @if ($order->status === OrderStatus::Paid)
                    <h1 class="co-h1">Pedido recibido</h1>
                    <p>Gracias, {{ \Illuminate\Support\Str::before($order->customer_name, ' ') }}. Tu pedido ha sido recibido. Te hemos enviado la confirmación a <strong>{{ $order->email }}</strong>.</p>
                @else
                    <h1 class="co-h1">Pedido {{ $order->reference }}</h1>
                @endif

                <ul class="co-overview" aria-label="Datos del pedido">
                    <li>Número del pedido<strong>{{ $order->reference }}</strong></li>
                    <li>Fecha<strong>{{ $order->paid_at?->timezone('Europe/Madrid')->translatedFormat('j \d\e F \d\e Y') }}</strong></li>
                    <li>Correo electrónico<strong>{{ $order->email }}</strong></li>
                    <li>Total<strong>{{ $total }}</strong></li>
                    <li>Método de pago<strong>{{ $paymentMethod }}</strong></li>
                </ul>

                @include('checkout.partials.order-body')
                <a class="co-link" href="{{ route('market') }}">Volver a Clave de Alma</a>
            @elseif ($order->status === OrderStatus::Cancelled)
                <h1 class="co-h1">Pedido cancelado</h1>
                @if ($order->paid_at)
                    <p>Hemos cancelado el pedido {{ $order->reference }} y te hemos devuelto {{ $total }} al mismo medio de pago. Según tu banco, puede tardar unos días en verse.</p>
                @else
                    <p>El pedido {{ $order->reference }} se canceló porque no se completó el pago. No se ha cobrado nada.</p>
                    <a class="co-btn" href="{{ route('cart.show') }}">Volver al carrito</a>
                @endif
            @else
                <h1 class="co-h1">Confirmando el pago…</h1>
                <p>Estamos esperando la confirmación del pago de tu pedido {{ $order->reference }}. Esta página se actualiza sola.</p>
                <a class="co-link" href="{{ route('checkout.pay', $order->token) }}">¿Algo ha fallado? Volver al pago</a>
            @endif
        </section>

        @include('checkout.partials.summary')
    </div>
@endsection
