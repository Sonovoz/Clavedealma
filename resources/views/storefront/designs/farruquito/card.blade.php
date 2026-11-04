{{-- Tarjeta de la pasarela con el ambiente de Farruquito (piezas enmarcadas en oro). Mismos datos que la común. --}}
@php
    $etiquetas = ['on-stage' => ['onstage', 'On Stage'], 'artist-voice' => ['artistvoice', 'Artist Voice'], 'iconic' => ['iconic', 'Iconic']];
    $col = $etiquetas[$card['collections'][0] ?? ''] ?? null;
@endphp
<article class="prod rv" style="--d:{{ $k % 4 }}" data-card data-collections="{{ implode(' ', $card['collections']) }}" data-type="{{ $card['type'] }}" data-price="{{ $card['price_cents'] }}" data-new="{{ $card['published'] }}" data-popular="{{ $card['sold'] }}" data-position="{{ $k }}">
    <a class="prod__vis tilt" href="{{ $card['url'] }}" aria-label="Ver {{ $card['name'] }}">
        @if ($card['reference'])<span class="prod__mod">{{ $card['reference'] }}</span>@endif
        @if ($col)<span class="prod__col prod__col--{{ $col[0] }}">{{ $col[1] }}</span>@endif
        @if ($card['image'])<img class="art" src="{{ $card['image'] }}" alt="{{ $card['name'] }}" loading="lazy">@endif
        @if ($card['hover'])<img class="look" src="{{ $card['hover'] }}" alt="" aria-hidden="true" loading="lazy">@endif
        <span class="prod__ver"><span class="btn">Ver la pieza</span></span>
    </a>
    <div class="prod__fila">
        <h3>{{ $card['name'] }}</h3>
        @include('storefront.partials.price', ['price' => $card['price'], 'discount' => $card['discount']])
    </div>
    <p class="tipo">{{ $card['garment'] }}</p>
</article>
