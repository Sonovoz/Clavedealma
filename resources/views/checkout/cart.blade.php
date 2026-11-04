@extends('checkout.layout', ['step' => 1])

@section('content')
    <h1 class="co-h1">Tu cesta</h1>

    @if (session('cart.error'))
        <p class="co-alert" role="alert">{{ session('cart.error') }}</p>
    @endif

    @if ($lines === [])
        <div class="co-card co-empty">
            <p>Tu cesta está vacía.</p>
            <a class="co-btn" href="{{ route('market') }}">Ver las tiendas</a>
        </div>
    @else
        <div class="co-grid">
            <section class="co-card" aria-label="Artículos">
                <ul class="co-lines">
                    @foreach ($lines as $line)
                        <li @class(['co-line', 'has-problem' => $line['problem']])>
                            <a class="co-line__img" href="{{ $line['url'] }}">@if ($line['image'])<img src="{{ $line['image'] }}" alt="{{ $line['name'] }}" loading="lazy">@endif</a>
                            <div class="co-line__info">
                                <a class="co-line__name" href="{{ $line['url'] }}">{{ $line['name'] }}</a>
                                <span class="co-line__meta">{{ $line['option'] }}</span>
                                <span class="co-line__store">{{ $line['store'] }}</span>
                                @if ($line['personalized'])<span class="co-line__meta">Pieza personalizada: sin derecho de desistimiento</span>@endif
                                @if ($line['problem'])<span class="co-line__problem">{{ $line['problem'] }}</span>@endif
                            </div>
                            <form class="co-line__qty" method="post" action="{{ route('cart.update') }}">
                                @csrf
                                <input type="hidden" name="variant_id" value="{{ $line['variant_id'] }}">
                                <label class="co-sr" for="qty-{{ $line['variant_id'] }}">Unidades de {{ $line['name'] }}</label>
                                <select id="qty-{{ $line['variant_id'] }}" name="quantity" data-autosubmit @disabled($line['quantity'] === 0 && $line['problem'])>
                                    @for ($q = 1; $q <= max(1, $line['max']); $q++)
                                        <option value="{{ $q }}" @selected($q === max(1, $line['quantity']))>{{ $q }}</option>
                                    @endfor
                                </select>
                                <noscript><button class="co-link" type="submit">Cambiar</button></noscript>
                            </form>
                            <span class="co-line__price">{{ $line['total'] }}<small>{{ $line['unit'] }} / ud.</small></span>
                            <form method="post" action="{{ route('cart.update') }}">
                                @csrf
                                <input type="hidden" name="variant_id" value="{{ $line['variant_id'] }}">
                                <input type="hidden" name="quantity" value="0">
                                <button class="co-link co-line__remove" type="submit">Quitar</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </section>

            <aside class="co-card co-summary" aria-label="Resumen">
                <dl class="co-totals">
                    <div><dt>Subtotal · {{ $count }} {{ $count === 1 ? 'pieza' : 'piezas' }}</dt><dd>{{ $subtotal }}</dd></div>
                    <div><dt>Envío</dt><dd>Se calcula con tu dirección</dd></div>
                </dl>
                @if ($hasProblems)
                    <p class="co-alert">Hay artículos que ya no se pueden comprar tal cual. Quítalos o cambia las unidades para seguir.</p>
                @endif
                @if ($canCheckout)
                    <a class="co-btn co-btn--full" href="{{ route('checkout.show') }}">Tramitar pedido</a>
                @else
                    <span class="co-btn co-btn--full" aria-disabled="true">Tramitar pedido</span>
                @endif
                <p class="co-fine">IVA incluido. Cada pieza se fabrica para ti bajo pedido.</p>
                <a class="co-link" href="{{ url()->previous() !== url()->current() ? url()->previous() : route('market') }}">Seguir comprando</a>
            </aside>
        </div>
    @endif
@endsection
