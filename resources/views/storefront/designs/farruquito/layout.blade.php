{{-- Diseño Farruquito: cabecera, menú y pie propios (portados de docs/diseno/farruquito). --}}
@extends('storefront.layouts.shop')

@php
    $a = fn (string $file): string => \App\Storefront\Urls::designAsset('farruquito', $file);
    $home = \App\Storefront\Urls::home($store);
@endphp

@section('body')
<div class="grano" aria-hidden="true"></div>
<div class="anillo" aria-hidden="true"></div>

<!-- Franja común: tienda dentro de Clave de Alma -->
<div class="cda">
  <div class="wrap">
    <a href="{{ route('market') }}" aria-label="Volver a Clave de Alma"><img src="{{ $a('logo-cda.png') }}" alt="">Una tienda de Clave de Alma</a>
    <span>Envío a península · Fabricado bajo pedido · Devolución en 14 días</span>
  </div>
</div>

<header class="cab" id="cab">
  <div class="wrap cab__in">
    <nav class="cab__nav" aria-label="Tienda">
      <a href="{{ $home }}#colecciones">Colecciones</a>
      <a href="{{ $home }}#principal">On Stage</a>
      <a href="{{ $home }}#artist-voice">Artist Voice</a>
      <a href="{{ $home }}#iconic">Iconic</a>
      <a href="{{ $home }}#coleccion">Tienda</a>
    </nav>
    <button class="ico menu-btn" type="button" id="menu-btn" aria-label="Abrir menú" aria-expanded="false"><svg viewBox="0 0 24 24"><path d="M4 8h16M4 16h16"/></svg></button>
    <a class="cab__logo" href="{{ $home }}" aria-label="Serás Farruquito, inicio"><img src="{{ $a('logo-farruquito.png') }}" alt="Serás Farruquito · El Musical" width="897" height="364"></a>
    <div class="cab__acc">
      <button class="ico" type="button" aria-label="Buscar"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/></svg></button>
      <a class="ico" href="{{ route('cart.show') }}" id="abrir-carrito" aria-label="Carrito ({{ $cartCount ?? 0 }})"><svg viewBox="0 0 24 24"><path d="M5 8h14l-1.2 12H6.2z"/><path d="M9 8V6.5a3 3 0 0 1 6 0V8"/></svg><span @class(['badge', 'on' => ($cartCount ?? 0) > 0]) id="badge" data-cart-count>{{ $cartCount ?? 0 }}</span></a>
    </div>
  </div>
</header>

<nav class="menu-movil" id="menu-movil" aria-label="Menú" hidden>
  <a href="{{ $home }}#colecciones">Colecciones</a><a href="{{ $home }}#principal">On Stage</a><a href="{{ $home }}#artist-voice">Artist Voice</a><a href="{{ $home }}#iconic">Iconic</a><a href="{{ $home }}#coleccion">Tienda</a>
</nav>


<main>
    @yield('content')
</main>

<!-- Pie común de Clave de Alma -->
<footer class="pie">
  <div class="wrap">
    <div class="pie__grid">
      <div class="pie__marca"><img src="{{ $a('logo-cda.png') }}" alt="Clave de Alma"><p>Tienda oficial de Serás Farruquito, dentro de Clave de Alma. Un solo carrito y un solo pago para todas las tiendas.</p></div>
      <div><h4>Esta tienda</h4><ul><li><a href="{{ $home }}#coleccion">Colección On Stage</a></li><li><a href="{{ $home }}#principal">Diseño principal</a></li><li><a href="{{ $home }}#lookbook">Lookbook</a></li></ul></div>
      <div><h4>Ayuda</h4><ul><li><a href="{{ $home }}#coleccion">Envíos</a></li><li><a href="{{ $home }}#coleccion">Devoluciones</a></li><li><a href="{{ $home }}#coleccion">Guía de tallas</a></li><li><a href="{{ $home }}#coleccion">Fabricación bajo pedido</a></li></ul></div>
      <div><h4>Pago seguro</h4><div class="pagos"><span>Tarjeta</span><span>Bizum</span><span>Apple Pay</span><span>Google Pay</span></div></div>
    </div>
    <div class="pie__legal"><span>© Clave de Alma · Premium Artists Platform</span>@include('storefront.partials.legal-links')</div>
  </div>
</footer>
@endsection
