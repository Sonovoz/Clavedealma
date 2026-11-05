@extends('checkout.layout', ['step' => 2])

{{-- «Te llevamos al pago seguro» (ADR 0026): el navegador envía el cobro, firmado por el
     servidor, a la página de pago de Redsys. Sin JavaScript, con el botón. --}}
@section('content')
    <h1 class="co-h1">Pago seguro</h1>

    <div class="co-grid">
        <section class="co-card co-redirect" aria-label="Pago">
            <p>
                Te llevamos a la página de pago segura de <strong>Redsys</strong>, la plataforma de pago de los bancos{{ $method ? ', para pagar con ' : '' }}
                @if ($method)<strong>{{ $method }}</strong>@endif.
            </p>
            <form method="post" action="{{ $action }}" data-autopay>
                @foreach ($fields as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach
                <button class="co-btn co-btn--full" type="submit">Ir a pagar {{ $total }}</button>
            </form>
            <p class="co-fine">Los datos de la tarjeta los escribes en Redsys: Clave de Alma no los ve ni los guarda. Al terminar volverás aquí, a tu pedido {{ $order->reference }}.</p>
            <a class="co-link" href="{{ route('checkout.pay', $order->token) }}">Elegir otro método de pago</a>
        </section>

        @include('checkout.partials.summary')
    </div>
@endsection
