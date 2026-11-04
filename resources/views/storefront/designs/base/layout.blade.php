{{-- Plantilla base: cabecera y pie sencillos. La usan las tiendas sin diseño propio. --}}
@extends('storefront.layouts.shop')

@section('body')
    <div class="base-strip">
        <div class="sf-wrap">
            <a href="{{ route('market') }}">Una tienda de Clave de Alma</a>
            <span>Fabricado bajo pedido · Devolución en 14 días</span>
        </div>
    </div>
    <header class="base-head">
        <div class="sf-wrap">
            <a class="base-head__name" href="{{ \App\Storefront\Urls::home($store) }}">{{ $store->name }}</a>
            <nav aria-label="Colecciones">
                @foreach ($collections as $collection)
                    <a href="{{ \App\Storefront\Urls::collection($store, $collection->slug) }}">{{ $collection->name }}</a>
                @endforeach
            </nav>
            <a class="base-head__cart" href="{{ route('cart.show') }}">Cesta (<span data-cart-count>{{ $cartCount ?? 0 }}</span>)</a>
        </div>
    </header>
    <main>
        @yield('content')
    </main>
    @include('storefront.partials.footer')
@endsection
