{{-- Matriz del market (versión en blanco; el diseño de la matriz llega en la capa visual). --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Clave de Alma · Tiendas oficiales de artistas</title>
    @vite(\App\Storefront\Designs::assets('base'))
</head>
<body>
    <main>
        <section class="market-hero">
            <div class="sf-wrap">
                <span class="sf-label">Premium Artists Platform</span>
                <h1 class="sf-display">Clave de Alma</h1>
                <p>Tiendas oficiales de artistas. Un solo carrito y un solo pago para todas.</p>
            </div>
        </section>
        <section class="sf-wrap">
            @if ($stores->isEmpty())
                <p class="sf-empty">Muy pronto, las primeras tiendas.</p>
            @else
                <div class="market-grid">
                    @foreach ($stores as $shop)
                        <a class="market-card" href="{{ \App\Storefront\Urls::home($shop) }}">
                            @if ($shop->status !== \App\Modules\Stores\Contracts\StoreStatus::Published)
                                <span class="market-badge">{{ $shop->status->label() }} · solo equipo</span>
                            @endif
                            <h2 class="sf-display">{{ $shop->name }}</h2>
                            @if ($shop->tagline)
                                <p>{{ $shop->tagline }}</p>
                            @endif
                        </a>
                    @endforeach
                </div>
            @endif
        </section>
    </main>
</body>
</html>
