@extends('checkout.layout', ['step' => 2])

{{-- «Finalizar compra» como WooCommerce: «Detalles de facturación» a la izquierda y, a la
     derecha, «Tu pedido», el pago y «Realizar el pedido». Los importes vienen del servidor. --}}
@php($stripe = $gateway === 'stripe' && $publicKey)

@if ($stripe)
    @push('head')
        {{-- Stripe.js siempre desde Stripe (requisito PCI): la tarjeta no pasa por nuestro servidor. --}}
        <script src="https://js.stripe.com/v3/" defer></script>
    @endpush
@endif

@section('content')
    <h1 class="co-h1">Finalizar compra</h1>

    @if (session('payment.error'))
        <p class="co-alert" role="alert">{{ session('payment.error') }}</p>
    @endif

    <form class="co-grid" method="post" action="{{ route('checkout.place') }}" novalidate
        data-checkout data-quote-url="{{ route('checkout.quote') }}" data-gateway="{{ $gateway }}"
        @if ($stripe) data-stripe-key="{{ $publicKey }}" @endif data-subtotal-cents="{{ $subtotalCents }}">
        @csrf
        <div class="co-card co-form">
            <p class="co-alert" role="alert" data-form-alert @if (! $errors->any()) hidden @endif>Revisa los datos marcados.</p>

            <fieldset>
                <legend class="co-h2">Detalles de facturación</legend>
                @include('checkout.partials.field', ['name' => 'name', 'label' => 'Nombre y apellidos', 'autocomplete' => 'name'])
                <div class="co-field">
                    <label for="country">País / Región</label>
                    <select id="country" name="country" autocomplete="country" data-quote-input>
                        @foreach ($countries as $code => $label)
                            <option value="{{ $code }}" @selected(old('country', 'ES') === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('country')<span class="co-error" id="country-error">{{ $message }}</span>@enderror
                </div>
                @include('checkout.partials.field', ['name' => 'address_line1', 'label' => 'Dirección de la calle', 'autocomplete' => 'address-line1', 'placeholder' => 'Nombre de la calle y número de la casa'])
                @include('checkout.partials.field', ['name' => 'address_line2', 'label' => 'Apartamento, habitación, etc. (opcional)', 'autocomplete' => 'address-line2', 'required' => false, 'placeholder' => 'Piso, escalera, puerta…'])
                <div class="co-row">
                    @include('checkout.partials.field', ['name' => 'postal_code', 'label' => 'Código postal', 'autocomplete' => 'postal-code', 'inputmode' => 'numeric', 'quote' => true])
                    @include('checkout.partials.field', ['name' => 'city', 'label' => 'Localidad / Ciudad', 'autocomplete' => 'address-level2'])
                </div>
                @include('checkout.partials.field', ['name' => 'province', 'label' => 'Región / Provincia (opcional)', 'autocomplete' => 'address-level1', 'required' => false])
                @include('checkout.partials.field', ['name' => 'phone', 'label' => 'Teléfono', 'type' => 'tel', 'autocomplete' => 'tel', 'hint' => 'Solo para la empresa de transporte.'])
                @include('checkout.partials.field', ['name' => 'email', 'label' => 'Dirección de correo electrónico', 'type' => 'email', 'autocomplete' => 'email', 'hint' => 'Aquí te enviamos la confirmación, la factura y el seguimiento.'])
            </fieldset>

            <fieldset>
                <legend class="co-h2">Información adicional</legend>
                <div class="co-field">
                    <label for="note">Notas del pedido (opcional)</label>
                    <textarea id="note" name="note" rows="3" maxlength="500" placeholder="Notas sobre tu pedido, por ejemplo, notas especiales para la entrega.">{{ old('note') }}</textarea>
                </div>
            </fieldset>
        </div>

        <aside class="co-card co-summary co-review" aria-label="Tu pedido">
            <h2 class="co-h2">Tu pedido</h2>
            <table class="co-review__table">
                <thead><tr><th>Producto</th><th class="co-num">Subtotal</th></tr></thead>
                <tbody>
                    @foreach ($lines as $line)
                        <tr>
                            <td>{{ $line['name'] }} <strong>× {{ $line['quantity'] }}</strong><small>{{ $line['option'] }} · {{ $line['store'] }}</small></td>
                            <td class="co-num">{{ $line['total'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr><th>Subtotal</th><td class="co-num">{{ $subtotal }}</td></tr>
                    <tr><th>Envío <small data-quote-zone></small></th><td class="co-num" data-quote-shipping>Escribe tu código postal</td></tr>
                    <tr class="co-review__total"><th>Total</th><td class="co-num" data-quote-total>{{ $subtotal }}</td></tr>
                </tfoot>
            </table>
            <p class="co-fine" data-quote-delivery>IVA incluido.</p>

            <div class="co-payment">
                @if ($stripe)
                    <p class="co-payment__title">Tarjeta, Apple Pay, Google Pay y otros métodos</p>
                    <div id="co-payment-element" class="co-stripe" data-payment-element>
                        <p class="co-fine">Cargando el pago seguro…</p>
                    </div>
                    <p class="co-fine">Los datos de pago los gestiona Stripe: Clave de Alma no los ve ni los guarda.</p>
                @elseif ($gateway === 'fake')
                    <div class="co-test-box">
                        <p><strong>Pago simulado (modo de pruebas).</strong> No se cobra nada.</p>
                        <label class="co-check"><input type="radio" name="simulate" value="ok" @checked(old('simulate', 'ok') === 'ok')><span>Simular pago correcto</span></label>
                        <label class="co-check"><input type="radio" name="simulate" value="ko" @checked(old('simulate') === 'ko')><span>Simular pago rechazado</span></label>
                    </div>
                @else
                    <p class="co-fine">Después de realizar el pedido pasarás a la página de pago.</p>
                @endif
            </div>

            <p class="co-fine">Tus datos personales se usarán para procesar tu pedido y para lo que explica nuestra <a href="{{ route('legal', 'privacidad') }}" target="_blank">política de privacidad</a>.</p>
            <label class="co-check">
                <input type="checkbox" name="terms" value="1" @checked(old('terms')) required @error('terms') aria-invalid="true" @enderror>
                <span>He leído y acepto las <a href="{{ route('legal', 'condiciones-de-venta') }}" target="_blank">condiciones de venta</a> y la <a href="{{ route('legal', 'privacidad') }}" target="_blank">política de privacidad</a>.</span>
            </label>
            @error('terms')<span class="co-error" id="terms-error">{{ $message }}</span>@enderror

            <p class="co-alert" id="co-pay-error" role="alert" hidden></p>
            <button class="co-btn co-btn--full" type="submit" data-place-order>Realizar el pedido</button>
            @if ($gateway === 'fake')
                <p class="co-fine co-test">Modo de pruebas: el pago será simulado y no se cobrará nada.</p>
            @endif
            <a class="co-link" href="{{ route('cart.show') }}">Volver al carrito</a>
        </aside>
    </form>
@endsection
