{{--
    PASARELA DE PRODUCTOS (común a todas las tiendas).
    Muestra los artículos publicados que el equipo de catálogo asigna a la tienda.
    Variables: $cards, $collections, $activeCollection, $store, $design.
    Opcionales: $heading, $intro, $label, $id (ancla, por defecto "tienda").
--}}
@php
    $cardView = \App\Storefront\Designs::view($design, 'card');
    $types = collect($cards)->pluck('type')->unique()->values();
    $typeLabels = ['camiseta' => 'Camisetas', 'sudadera' => 'Sudaderas', 'gorra' => 'Gorras', 'tote' => 'Tote bags', 'otros' => 'Otros'];
@endphp
<section class="sf-showcase" id="{{ $id ?? 'tienda' }}" data-showcase>
    <div class="sf-wrap">
        <div class="sf-showcase__head">
            <span class="sf-label">{{ $label ?? 'Tienda oficial' }}</span>
            <h2 class="sf-display">{{ $heading ?? ($activeCollection?->name ?? 'Todas las piezas') }}</h2>
            @if (($intro ?? $activeCollection?->description) !== null)
                <p>{{ $intro ?? $activeCollection?->description }}</p>
            @endif
        </div>

        @if ($activeCollection === null && $collections->count() > 1)
            <div class="sf-filters" role="group" aria-label="Filtrar por colección">
                <button class="sf-chip" type="button" data-filter="collection" data-value="todas" aria-pressed="true">Todas</button>
                @foreach ($collections as $collection)
                    <button class="sf-chip" type="button" data-filter="collection" data-value="{{ $collection->slug }}" aria-pressed="false">{{ $collection->name }}</button>
                @endforeach
            </div>
        @elseif ($activeCollection !== null)
            <nav class="sf-filters" aria-label="Colecciones">
                <a class="sf-chip" href="{{ \App\Storefront\Urls::home($store) }}#{{ $id ?? 'tienda' }}">Todas</a>
                @foreach ($collections as $collection)
                    <a class="sf-chip" href="{{ \App\Storefront\Urls::collection($store, $collection->slug) }}" @if ($collection->is($activeCollection)) aria-current="page" @endif>{{ $collection->name }}</a>
                @endforeach
            </nav>
        @endif

        @if ($types->count() > 1)
            <div class="sf-filters sf-filters--type" role="group" aria-label="Filtrar por tipo de prenda">
                <button class="sf-chip" type="button" data-filter="type" data-value="todo" aria-pressed="true">Todo</button>
                @foreach ($types as $type)
                    <button class="sf-chip" type="button" data-filter="type" data-value="{{ $type }}" aria-pressed="false">{{ $typeLabels[$type] ?? \Illuminate\Support\Str::ucfirst($type) }}</button>
                @endforeach
            </div>
        @endif

        {{-- Como en WooCommerce: recuento de resultados y «Ordenar por». --}}
        <div class="sf-toolbar">
            <p class="sf-count" data-count aria-live="polite">{{ count($cards) === 1 ? 'Mostrando el único resultado' : 'Mostrando los '.count($cards).' resultados' }}</p>
            @if (count($cards) > 1)
                <label class="sf-orderby">
                    <span class="sf-sr">Ordenar por</span>
                    <select data-orderby>
                        <option value="menu_order">Orden predeterminado</option>
                        <option value="popularity">Ordenar por popularidad</option>
                        <option value="date">Ordenar por los últimos</option>
                        <option value="price">Ordenar por precio: bajo a alto</option>
                        <option value="price-desc">Ordenar por precio: alto a bajo</option>
                    </select>
                </label>
            @endif
        </div>

        @if ($cards === [])
            <p class="sf-empty">Pronto habrá piezas en esta tienda.</p>
        @else
            <div class="sf-grid {{ $gridClass ?? '' }}">
                @foreach ($cards as $k => $card)
                    @include($cardView, ['card' => $card, 'k' => $k])
                @endforeach
            </div>
            <nav class="sf-pagination" data-pagination aria-label="Páginas de productos" hidden></nav>
        @endif
        <p class="sf-note">IVA incluido · Fabricado bajo pedido · Devolución en 14 días</p>
    </div>
</section>
