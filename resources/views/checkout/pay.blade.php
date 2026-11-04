@extends('checkout.layout', ['step' => 3])

@if ($gateway === 'stripe' && $clientSecret)
    @push('head')
        {{-- Stripe.js siempre desde Stripe (requisito PCI): la tarjeta no pasa por nuestro servidor. --}}
        <script src="https://js.stripe.com/v3/" defer></script>
    @endpush
@endif

@section('content')
    <h1 class="co-h1">Pago</h1>

    <div class="co-grid">
        <section class="co-card" aria-label="Pago">
            @if (session('payment.error'))
                <p class="co-alert" role="alert">{{ session('payment.error') }}</p>
            @endif

            @if ($unavailable)
                <p class="co-alert" role="alert">Ahora mismo no podemos conectar con el sistema de pago. Tu pedido está guardado: vuelve a intentarlo en unos minutos.</p>
                <a class="co-btn" href="{{ route('checkout.pay', $order->token) }}">Reintentar</a>
            @elseif ($gateway === 'stripe')
                <form id="co-pay" data-stripe-key="{{ $publicKey }}" data-client-secret="{{ $clientSecret }}" data-return-url="{{ $returnUrl }}" data-email="{{ $order->email }}">
                    <div id="co-payment-element" class="co-stripe"></div>
                    <p class="co-error" id="co-pay-error" role="alert" hidden></p>
                    <button class="co-btn co-btn--full" type="submit" id="co-pay-button">Pagar {{ $total }}</button>
                </form>
                <p class="co-fine">Tarjeta, Apple Pay, Google Pay y los demás métodos que ofrezca Stripe. Los datos de pago los gestiona Stripe: Clave de Alma no los ve ni los guarda.</p>
            @else
                <div class="co-test-box">
                    <p><strong>Pago simulado (modo de pruebas).</strong> No se cobra nada. Sirve para probar la compra completa: pedido, stock y emails.</p>
                    <form method="post" action="{{ route('checkout.simulate', $order->token) }}">
                        @csrf
                        <button class="co-btn co-btn--full" type="submit" name="outcome" value="ok">Simular pago correcto · {{ $total }}</button>
                        <button class="co-btn co-btn--ghost co-btn--full" type="submit" name="outcome" value="ko">Simular pago rechazado</button>
                    </form>
                </div>
            @endif

            <div class="co-ship">
                <h2 class="co-h2">Entrega</h2>
                <p>{{ $order->customer_name }}<br>{{ $order->address_line1 }}@if ($order->address_line2), {{ $order->address_line2 }}@endif<br>{{ $order->postal_code }} {{ $order->city }} · {{ \App\Storefront\Checkout\Countries::ALL[$order->country] ?? $order->country }}</p>
                <p class="co-fine">{{ $order->email }} · {{ $order->phone }}</p>
            </div>
        </section>

        @include('checkout.partials.summary')
    </div>
@endsection
