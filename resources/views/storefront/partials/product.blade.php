{{--
    FICHA DE ARTÍCULO (común a todas las tiendas). Variables: $item, $store.
    Precio, rebaja, tallas disponibles y colores vienen calculados de StoreCatalog.
--}}
@php
    $firstColor = $item['colors'][0]['name'] ?? null;
    $garment = $item['garment'];
    $hasGuide = $garment && count($garment['sizes']) > 0;
@endphp
<section class="sf-product" data-product>
    <div class="sf-wrap">
        <nav class="sf-crumbs" aria-label="Estás en">
            <a href="{{ \App\Storefront\Urls::home($store) }}">{{ $store->name }}</a>
            @foreach ($item['collections'] as $collection)
                <span aria-hidden="true">/</span><a href="{{ $collection['url'] }}">{{ $collection['name'] }}</a>
            @endforeach
        </nav>

        <div class="sf-product__grid">
            <div class="sf-gallery">
                <div class="sf-gallery__main">
                    @if ($item['images'] !== [])
                        <img data-main-image src="{{ $item['images'][0]['url'] }}" alt="{{ $item['images'][0]['alt'] }}">
                    @endif
                </div>
                @if (count($item['images']) > 1)
                    <div class="sf-gallery__thumbs">
                        @foreach ($item['images'] as $k => $image)
                            <button class="sf-thumb" type="button" data-thumb data-src="{{ $image['url'] }}" data-alt="{{ $image['alt'] }}" data-color="{{ $image['color'] }}" aria-current="{{ $k === 0 ? 'true' : 'false' }}" aria-label="Ver foto {{ $k + 1 }}">
                                <img src="{{ $image['url'] }}" alt="" loading="lazy">
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="sf-buy">
                <h1 class="sf-display" data-title>{{ $item['name'] }}@if ($firstColor) | {{ mb_strtoupper($firstColor) }}@endif</h1>
                @if ($item['reference'])
                    <p class="sf-buy__ref">Ref. {{ $item['reference'] }}</p>
                @endif

                <div data-price>
                    @include('storefront.partials.price', ['price' => $item['price'], 'discount' => $item['before'] ? ['before' => $item['before'], 'percent' => $item['percent']] : null])
                </div>
                <p class="sf-buy__tax">IVA incluido · {{ $item['lead_time'] }}</p>

                @if (count($item['colors']) > 0)
                    <div class="sf-option">
                        <div class="sf-option__head"><span class="sf-label">Color</span><span data-color-name>{{ $firstColor }}</span></div>
                        <div class="sf-swatches" role="group" aria-label="Elige color">
                            @foreach ($item['colors'] as $color)
                                <button class="sf-swatch" type="button" data-color="{{ $color['name'] }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}" aria-label="{{ $color['name'] }}" title="{{ $color['name'] }}"
                                    style="{{ $color['swatch'] ? "background-image:url('{$color['swatch']}')" : 'background:'.($color['hex'] ?? '#999') }}"></button>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($item['sizes'] !== [])
                    <div class="sf-option">
                        <div class="sf-option__head">
                            <span class="sf-label">Selecciona una talla</span>
                            @if ($hasGuide)
                                <button class="sf-link" type="button" data-open-guide>Guía de tallas</button>
                            @endif
                        </div>
                        <div class="sf-sizes" role="group" aria-label="Elige talla">
                            @foreach ($item['sizes'] as $size)
                                <button class="sf-size" type="button" data-size="{{ $size }}" aria-pressed="false">{{ $size }}</button>
                            @endforeach
                        </div>
                    </div>
                @endif

                <p class="sf-stock" data-stock aria-live="polite"></p>
                {{-- Como WooCommerce: cantidad + «Añadir al carrito». --}}
                <form class="sf-add-form" method="post" action="{{ route('cart.add') }}" data-add-form>
                    @csrf
                    <input type="hidden" name="variant_id" value="" data-variant-input>
                    <div class="sf-cart-row">
                        <label class="sf-sr" for="sf-qty">Cantidad</label>
                        <input class="sf-qty" id="sf-qty" type="number" name="quantity" value="1" min="1" max="10" step="1" inputmode="numeric" data-qty>
                        <button class="sf-add" type="submit" data-add disabled>{{ $item['sizes'] !== [] ? 'Elige una talla' : 'Añadir al carrito' }}</button>
                    </div>
                </form>
                <p class="sf-added" data-add-message aria-live="polite" @if (! session('cart.added') && ! session('cart.error')) hidden @endif>
                    {{ session('cart.added') ?? session('cart.error') }} <a href="{{ route('cart.show') }}">Ver carrito</a>
                </p>

                {{-- Datos del producto, como el pie de ficha de WooCommerce. --}}
                <p class="sf-meta">
                    @if ($item['reference'])<span>SKU: <strong data-sku>{{ $item['reference'] }}</strong></span>@endif
                    @if ($item['collections'] !== [])
                        <span>{{ count($item['collections']) === 1 ? 'Categoría' : 'Categorías' }}:
                            @foreach ($item['collections'] as $collection)<a href="{{ $collection['url'] }}">{{ $collection['name'] }}</a>@if (! $loop->last), @endif @endforeach
                        </span>
                    @endif
                    <span>Tienda: <a href="{{ \App\Storefront\Urls::home($store) }}">{{ $store->name }}</a></span>
                </p>
            </div>
        </div>

        {{-- Pestañas como WooCommerce: Descripción · Información adicional · Envíos y devoluciones. --}}
        <div class="sf-tabs" data-tabs>
            <div class="sf-tabs__list" role="tablist">
                <button class="sf-tab" type="button" role="tab" id="tab-desc" aria-controls="panel-desc" aria-selected="true" data-tab>Descripción</button>
                <button class="sf-tab" type="button" role="tab" id="tab-info" aria-controls="panel-info" aria-selected="false" data-tab>Información adicional</button>
                <button class="sf-tab" type="button" role="tab" id="tab-ship" aria-controls="panel-ship" aria-selected="false" data-tab>Envíos y devoluciones</button>
            </div>
            <div class="sf-tabs__panel" role="tabpanel" id="panel-desc" aria-labelledby="tab-desc">
                @if ($item['description'])
                    <div class="sf-desc">{!! $item['description'] !!}</div>
                @else
                    <p class="sf-desc">{{ $item['name'] }}{{ $garment ? ' · '.$garment['name'] : '' }}, merchandising oficial de {{ $store->name }}.</p>
                @endif
                @if ($item['model_info'])
                    <p class="sf-model">{{ $item['model_info'] }}</p>
                @endif
            </div>
            <div class="sf-tabs__panel" role="tabpanel" id="panel-info" aria-labelledby="tab-info" hidden>
                <ul class="sf-facts">
                    @if ($item['colors'] !== [])<li><span>Colores</span><span>{{ collect($item['colors'])->pluck('name')->implode(', ') }}</span></li>@endif
                    @if ($item['sizes'] !== [])<li><span>Tallas</span><span>{{ implode(', ', $item['sizes']) }}</span></li>@endif
                    @if ($garment && $garment['composition'])<li><span>Composición</span><span>{{ $garment['composition'] }}</span></li>@endif
                    @if ($garment && $garment['material'])<li><span>Tejido</span><span>{{ $garment['material'] }}</span></li>@endif
                    @if ($garment && $garment['fit'])<li><span>Corte</span><span>{{ $garment['fit'] }}</span></li>@endif
                    @if ($garment && $garment['care'])<li><span>Cuidados</span><span>{{ $garment['care'] }}</span></li>@endif
                    @if ($garment && $garment['origin'])<li><span>Fabricado en</span><span>{{ $garment['origin'] }}</span></li>@endif
                    <li><span>Fabricación</span><span>Bajo pedido · {{ \Illuminate\Support\Str::lcfirst($item['lead_time']) }}</span></li>
                    <li><span>Devoluciones</span><span>{{ $item['personalized'] ? 'Pieza personalizada: sin derecho de desistimiento' : '14 días desde la entrega' }}</span></li>
                </ul>
                @if ($hasGuide)
                    <button class="sf-link" type="button" data-open-guide>Ver la guía de tallas</button>
                @endif
            </div>
            <div class="sf-tabs__panel" role="tabpanel" id="panel-ship" aria-labelledby="tab-ship" hidden>
                <p>Cada pieza se fabrica para ti: {{ \Illuminate\Support\Str::lcfirst($item['lead_time']) }}. Después sale con la empresa de transporte de tu destino; verás el precio del envío en el carrito antes de pagar.</p>
                <p>{{ $item['personalized'] ? 'Al ser una pieza personalizada no admite devolución, salvo defecto.' : 'Tienes 14 días desde la entrega para devolverla desde la página de tu pedido.' }}
                    <a href="{{ \App\Storefront\Urls::legal($store, 'envios-y-devoluciones') }}">Envíos y devoluciones</a></p>
            </div>
        </div>

        @if (($related ?? []) !== [])
            <section class="sf-related" aria-labelledby="sf-related-title">
                <h2 class="sf-display" id="sf-related-title">Productos relacionados</h2>
                <div class="sf-grid">
                    @foreach ($related as $k => $card)
                        @include(\App\Storefront\Designs::view($design, 'card'), ['card' => $card, 'k' => $k])
                    @endforeach
                </div>
            </section>
        @endif
    </div>

    @if ($hasGuide)
        <dialog class="sf-dialog" id="sf-size-guide" aria-labelledby="sf-size-guide-title">
            <button class="sf-dialog__close" type="button" data-close-guide aria-label="Cerrar">×</button>
            <h2 class="sf-display" id="sf-size-guide-title">Guía de tallas</h2>
            <p class="sf-label" style="margin:0 0 14px">{{ $garment['name'] }} · medidas en cm, en plano</p>
            <table class="sf-table">
                <thead><tr><th>Talla</th><th>Ancho de pecho</th><th>Largo</th><th>Manga</th></tr></thead>
                <tbody>
                    @foreach ($garment['sizes'] as $row)
                        <tr><td>{{ $row['size'] }}</td><td>{{ $row['chest'] ?? '—' }}</td><td>{{ $row['length'] ?? '—' }}</td><td>{{ $row['sleeve'] ?? '—' }}</td></tr>
                    @endforeach
                </tbody>
            </table>
            @if ($garment['note'])
                <p class="sf-note" style="text-align:left">{{ $garment['note'] }}</p>
            @endif
        </dialog>
    @endif

    <script type="application/json" id="sf-product-data">@json(['name' => $item['name'], 'variants' => $item['variants'], 'colors' => $item['colors']])</script>
</section>
