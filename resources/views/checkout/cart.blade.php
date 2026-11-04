@extends('checkout.layout', ['step' => 1])

{{-- Carrito como WooCommerce: tabla de productos, «Actualizar carrito», «Totales del carrito»
     con calculadora de envío y «Finalizar compra». Todos los importes vienen del servidor. --}}
@section('content')
    <h1 class="co-h1">Carrito</h1>

    @if (session('cart.error'))
        <p class="co-alert" role="alert">{{ session('cart.error') }}</p>
    @endif
    @if (session('cart.updated'))
        <p class="co-ok" role="status">{{ session('cart.updated') }}</p>
    @endif

    @if ($lines === [])
        <div class="co-card co-empty">
            <p>Tu carrito está vacío.</p>
            <a class="co-btn" href="{{ route('market') }}">Volver a la tienda</a>
        </div>
    @else
        <div class="co-grid">
            <form class="co-card co-cart" method="post" action="{{ route('cart.update') }}" aria-label="Productos del carrito">
                @csrf
                <table class="co-cart__table">
                    <thead>
                        <tr>
                            <th class="co-cart__remove"><span class="co-sr">Quitar</span></th>
                            <th class="co-cart__thumb"><span class="co-sr">Imagen</span></th>
                            <th>Producto</th>
                            <th class="co-num co-cart__price">Precio</th>
                            <th class="co-num">Cantidad</th>
                            <th class="co-num">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lines as $line)
                            <tr @class(['has-problem' => $line['problem']])>
                                <td class="co-cart__remove">
                                    <button class="co-x" type="submit" name="remove" value="{{ $line['variant_id'] }}" aria-label="Quitar {{ $line['name'] }} del carrito">×</button>
                                </td>
                                <td class="co-cart__thumb"><a href="{{ $line['url'] }}">@if ($line['image'])<img src="{{ $line['image'] }}" alt="" loading="lazy">@endif</a></td>
                                <td>
                                    <a class="co-line__name" href="{{ $line['url'] }}">{{ $line['name'] }}</a>
                                    <span class="co-line__meta">{{ $line['option'] }}</span>
                                    <span class="co-line__store">{{ $line['store'] }}</span>
                                    @if ($line['personalized'])<span class="co-line__meta">Pieza personalizada: sin derecho de desistimiento</span>@endif
                                    @if ($line['problem'])<span class="co-line__problem">{{ $line['problem'] }}</span>@endif
                                </td>
                                <td class="co-num co-cart__price">{{ $line['unit'] }}</td>
                                <td class="co-num">
                                    <label class="co-sr" for="qty-{{ $line['variant_id'] }}">Cantidad de {{ $line['name'] }}</label>
                                    <input class="co-qty" id="qty-{{ $line['variant_id'] }}" type="number" name="quantities[{{ $line['variant_id'] }}]"
                                        value="{{ max(1, $line['quantity']) }}" min="0" max="{{ max(1, $line['max']) }}" inputmode="numeric" data-cart-qty>
                                </td>
                                <td class="co-num"><strong>{{ $line['total'] }}</strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="co-cart__actions">
                    <a class="co-link" href="{{ url()->previous() !== url()->current() ? url()->previous() : route('market') }}">← Seguir comprando</a>
                    <button class="co-btn co-btn--ghost" type="submit" name="update" value="1" data-update-cart>Actualizar carrito</button>
                </div>
            </form>

            <aside class="co-card co-summary" aria-label="Totales del carrito" data-cart-totals data-quote-url="{{ route('checkout.quote') }}">
                <h2 class="co-h2">Totales del carrito</h2>
                <dl class="co-totals">
                    <div><dt>Subtotal</dt><dd>{{ $subtotal }}</dd></div>
                    <div>
                        <dt>Envío</dt>
                        <dd data-shipping-result>Se calcula con tu dirección</dd>
                    </div>
                </dl>
                <details class="co-calc">
                    <summary class="co-link">Calcular envío</summary>
                    <div class="co-calc__form">
                        <div class="co-field">
                            <label for="calc-country">País</label>
                            <select id="calc-country" data-calc-country>
                                @foreach ($countries as $code => $label)
                                    <option value="{{ $code }}" @selected($code === 'ES')>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="co-field">
                            <label for="calc-postal">Código postal</label>
                            <input id="calc-postal" type="text" inputmode="numeric" autocomplete="postal-code" data-calc-postal>
                        </div>
                        <button class="co-btn co-btn--ghost co-btn--full" type="button" data-calc-shipping>Actualizar</button>
                    </div>
                </details>
                <dl class="co-totals">
                    <div class="co-totals__total"><dt>Total</dt><dd data-total-result data-subtotal="{{ $subtotal }}">{{ $subtotal }}</dd></div>
                </dl>
                <p class="co-fine">IVA incluido. Cada pieza se fabrica para ti bajo pedido.</p>
                @if ($hasProblems)
                    <p class="co-alert">Hay artículos que no se pueden comprar tal cual. Quítalos o cambia las cantidades y pulsa «Actualizar carrito».</p>
                @endif
                @if ($canCheckout)
                    <a class="co-btn co-btn--full" href="{{ route('checkout.show') }}">Finalizar compra</a>
                @else
                    <span class="co-btn co-btn--full" aria-disabled="true">Finalizar compra</span>
                @endif
            </aside>
        </div>
    @endif
@endsection
