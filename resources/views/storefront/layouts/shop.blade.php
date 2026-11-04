{{--
    Estructura común de todas las tiendas. Cada diseño la extiende y rellena:
    @section('head')   etiquetas extra en <head> (opcional)
    @section('body')   cabecera, contenido y pie del diseño
    La pasarela (storefront.partials.showcase) y la ficha (storefront.partials.product)
    son comunes: el diseño decide dónde van, no cómo funcionan.
--}}
@php($assetBase = \Illuminate\Support\Str::beforeLast(\App\Storefront\Urls::designAsset($design, 'x.png'), 'x.png'))
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    @include('storefront.partials.seo')
    @yield('head')
    @vite($assets)
</head>
<body class="sf sf--{{ $design }}" data-assets="{{ $assetBase }}">
    @include('storefront.partials.preview')
    @yield('body')
</body>
</html>
