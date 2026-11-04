{{-- Tarjeta común de la pasarela. Un diseño puede tener la suya en designs/<clave>/card.blade.php --}}
<a class="sf-card" href="{{ $card['url'] }}" data-card data-collections="{{ implode(' ', $card['collections']) }}" data-type="{{ $card['type'] }}" data-price="{{ $card['price_cents'] }}" data-new="{{ $card['published'] }}" data-popular="{{ $card['sold'] }}" data-position="{{ $k }}">
    <div class="sf-card__img">
        @if ($card['image'])
            <img src="{{ $card['image'] }}" alt="{{ $card['name'] }}" loading="lazy">
        @endif
        @if ($card['hover'])
            <img class="sf-card__hover" src="{{ $card['hover'] }}" alt="" loading="lazy" aria-hidden="true">
        @endif
    </div>
    <h3 class="sf-card__name">{{ $card['name'] }}</h3>
    <div class="sf-card__row">
        @include('storefront.partials.price', ['price' => $card['price'], 'discount' => $card['discount']])
        @include('storefront.partials.dots', ['colors' => $card['colors']])
    </div>
</a>
