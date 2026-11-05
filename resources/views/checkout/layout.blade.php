{{--
    Compra (cesta, datos, pago y confirmación): común a todas las tiendas, con la imagen
    de Clave de Alma, que es quien vende y cobra. Variables: $seo, $step (1-3).
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    @include('storefront.partials.seo')
    @stack('head')
    @vite(['resources/css/storefront/checkout.css', 'resources/js/storefront/checkout.js'])
</head>
<body class="co">
    <header class="co-head">
        <div class="co-wrap co-head__in">
            <a class="co-brand" href="{{ route('market') }}">Clave de Alma</a>
            @if ($step > 0)
            <ol class="co-steps" aria-label="Pasos de la compra">
                @foreach (['Carrito', 'Finalizar compra', 'Pedido recibido'] as $k => $label)
                    <li @class(['is-done' => $k + 1 < $step, 'is-on' => $k + 1 === $step]) @if ($k + 1 === $step) aria-current="step" @endif><span>{{ $k + 1 }}</span>{{ $label }}</li>
                @endforeach
            </ol>
            <span class="co-secure">Pago seguro</span>
            @else
            <a class="co-secure" href="{{ route('cart.show') }}">Carrito</a>
            @endif
        </div>
    </header>
    <main class="co-wrap co-main">
        @yield('content')
    </main>
    <footer class="co-foot">
        <div class="co-wrap">
            <span>Clave de Alma vende y factura todas las piezas de sus tiendas oficiales. Un solo carrito y un solo pago.</span>
            <nav aria-label="Información legal">
                <a href="{{ route('legal', 'condiciones-de-venta') }}">Condiciones de venta</a>
                <a href="{{ route('legal', 'envios-y-devoluciones') }}">Envíos y devoluciones</a>
                <a href="{{ route('legal', 'privacidad') }}">Privacidad</a>
                <a href="{{ route('legal', 'cookies') }}">Cookies</a>
                <a href="{{ route('legal', 'aviso-legal') }}">Aviso legal</a>
            </nav>
        </div>
    </footer>
</body>
</html>
