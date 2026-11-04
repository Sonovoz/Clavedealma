{{-- Pie común de Clave de Alma (vendedor único): igual en todas las tiendas que no tengan pie propio. --}}
<footer class="base-foot">
    <div class="sf-wrap">
        <span>{{ $store->name }} es una tienda de Clave de Alma. Un solo carrito y un solo pago para todas las tiendas.</span>
        <span>Tarjeta · Bizum · Apple Pay · Google Pay</span>
        @include('storefront.partials.legal-links')
    </div>
</footer>
