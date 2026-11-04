{{-- Metaetiquetas para buscadores y redes sociales (App\Storefront\Seo\SeoBuilder, docs/seo). --}}
<title>{{ $seo->title }}</title>
<meta name="description" content="{{ $seo->description }}">
<meta name="robots" content="{{ $seo->robots() }}">
<link rel="canonical" href="{{ $seo->canonical }}">
<link rel="alternate" hreflang="es-ES" href="{{ $seo->canonical }}">
<meta property="og:site_name" content="Clave de Alma">
<meta property="og:locale" content="es_ES">
<meta property="og:type" content="{{ $seo->type }}">
<meta property="og:title" content="{{ $seo->title }}">
<meta property="og:description" content="{{ $seo->description }}">
<meta property="og:url" content="{{ $seo->canonical }}">
@if ($seo->image)
    <meta property="og:image" content="{{ $seo->image }}">
    <meta name="twitter:image" content="{{ $seo->image }}">
@endif
@foreach ($seo->extraOg as $property => $content)
    <meta property="{{ $property }}" content="{{ $content }}">
@endforeach
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seo->title }}">
<meta name="twitter:description" content="{{ $seo->description }}">
@foreach ($seo->jsonLd as $block)
    <script type="application/ld+json">{!! json_encode($block, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endforeach
