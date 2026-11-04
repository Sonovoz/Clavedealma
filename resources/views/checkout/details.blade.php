@extends('checkout.layout', ['step' => 2])

@section('content')
    <h1 class="co-h1">Datos y envío</h1>

    <div class="co-grid">
        <form class="co-card co-form" method="post" action="{{ route('checkout.place') }}" data-checkout data-quote-url="{{ route('checkout.quote') }}" novalidate>
            @csrf
            @if ($errors->any())
                <p class="co-alert" role="alert">Revisa los datos marcados.</p>
            @endif

            <fieldset>
                <legend class="co-h2">Contacto</legend>
                @include('checkout.partials.field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'autocomplete' => 'email', 'hint' => 'Aquí te enviamos la confirmación y el seguimiento.'])
                @include('checkout.partials.field', ['name' => 'phone', 'label' => 'Teléfono', 'type' => 'tel', 'autocomplete' => 'tel', 'hint' => 'Solo para la empresa de transporte.'])
            </fieldset>

            <fieldset>
                <legend class="co-h2">Dirección de entrega</legend>
                @include('checkout.partials.field', ['name' => 'name', 'label' => 'Nombre y apellidos', 'autocomplete' => 'name'])
                <div class="co-field">
                    <label for="country">País</label>
                    <select id="country" name="country" autocomplete="country" data-quote-input>
                        @foreach ($countries as $code => $label)
                            <option value="{{ $code }}" @selected(old('country', 'ES') === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('country')<span class="co-error">{{ $message }}</span>@enderror
                </div>
                @include('checkout.partials.field', ['name' => 'address_line1', 'label' => 'Dirección', 'autocomplete' => 'address-line1', 'placeholder' => 'Calle, número, piso'])
                @include('checkout.partials.field', ['name' => 'address_line2', 'label' => 'Más datos (opcional)', 'autocomplete' => 'address-line2', 'required' => false, 'placeholder' => 'Escalera, puerta, urbanización…'])
                <div class="co-row">
                    @include('checkout.partials.field', ['name' => 'postal_code', 'label' => 'Código postal', 'autocomplete' => 'postal-code', 'inputmode' => 'numeric', 'quote' => true])
                    @include('checkout.partials.field', ['name' => 'city', 'label' => 'Población', 'autocomplete' => 'address-level2'])
                </div>
                @include('checkout.partials.field', ['name' => 'province', 'label' => 'Provincia (opcional)', 'autocomplete' => 'address-level1', 'required' => false])
                <div class="co-field">
                    <label for="note">Nota para el pedido (opcional)</label>
                    <textarea id="note" name="note" rows="2" maxlength="500">{{ old('note') }}</textarea>
                </div>
            </fieldset>

            <label class="co-check">
                <input type="checkbox" name="terms" value="1" @checked(old('terms')) required>
                <span>He leído y acepto las <a href="{{ route('legal', 'condiciones-de-venta') }}" target="_blank">condiciones de venta</a> y la <a href="{{ route('legal', 'privacidad') }}" target="_blank">política de privacidad</a>.</span>
            </label>
            @error('terms')<span class="co-error">{{ $message }}</span>@enderror

            <button class="co-btn co-btn--full" type="submit">Continuar al pago</button>
            @if ($gateway === 'fake')
                <p class="co-fine co-test">Modo de pruebas: el pago será simulado y no se cobrará nada.</p>
            @endif
        </form>

        <aside class="co-card co-summary" aria-label="Resumen">
            <h2 class="co-h2">Tu pedido</h2>
            <ul class="co-mini">
                @foreach ($lines as $line)
                    <li>
                        <span class="co-mini__img">@if ($line['image'])<img src="{{ $line['image'] }}" alt="" loading="lazy">@endif<b>{{ $line['quantity'] }}</b></span>
                        <span class="co-mini__txt"><strong>{{ $line['name'] }}</strong><small>{{ $line['option'] }} · {{ $line['store'] }}</small></span>
                        <span class="co-mini__price">{{ $line['total'] }}</span>
                    </li>
                @endforeach
            </ul>
            <dl class="co-totals">
                <div><dt>Subtotal</dt><dd>{{ $subtotal }}</dd></div>
                <div><dt>Envío <small data-quote-zone></small></dt><dd data-quote-shipping>Escribe tu código postal</dd></div>
                <div class="co-totals__total"><dt>Total</dt><dd data-quote-total>{{ $subtotal }}</dd></div>
            </dl>
            <p class="co-fine" data-quote-delivery>IVA incluido.</p>
            <a class="co-link" href="{{ route('cart.show') }}">Volver al carrito</a>
        </aside>
    </div>
@endsection
