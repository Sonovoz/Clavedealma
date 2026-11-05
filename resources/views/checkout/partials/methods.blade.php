{{-- «Métodos de pago», como en WooCommerce (ADR 0026): se elige uno y se paga en Redsys. --}}
<ul class="co-methods" aria-label="Métodos de pago">
    @foreach ($methods as $key => $label)
        <li class="co-method">
            <label class="co-check">
                <input type="radio" name="payment_method" value="{{ $key }}" @checked(old('payment_method', array_key_first($methods)) === $key)>
                <span>{{ $label }}</span>
            </label>
            <p class="co-method__box">
                @if ($key === 'bizum')
                    Paga desde el móvil: confirmas el pago en la app de tu banco con Bizum.
                @else
                    Visa, Mastercard y las demás tarjetas, en la página de pago segura de Redsys (la plataforma de los bancos).
                @endif
            </p>
        </li>
    @endforeach
</ul>
@error('payment_method')<span class="co-error">{{ $message }}</span>@enderror
<p class="co-fine">Los datos de la tarjeta los escribes en Redsys: Clave de Alma no los ve ni los guarda.</p>
@if ($testMode ?? false)
    <p class="co-fine co-test">Modo de pruebas de Redsys: no se cobra nada. Usa la tarjeta de prueba 4548 8100 0000 0003, caducidad 12/49, CVV 123.</p>
@endif
