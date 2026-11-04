{{-- Franja grande «Tienda: X» en las páginas del catálogo (se cambia en el selector de arriba). --}}
@php($store = \App\Filament\Support\ActiveStore::get())
<div role="status" style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.75rem 1.5rem;margin:1rem 0 1.25rem;padding:1rem 1.25rem;border-radius:.75rem;border:2px solid rgb(245 158 11);background:rgba(245,158,11,.12)">
    @if ($store)
        <div style="font-size:1.75rem;line-height:1.15;font-weight:800;letter-spacing:-.01em">
            Tienda: <span style="color:rgb(217 119 6)">{{ $store->name }}</span>
        </div>
        <div style="font-size:.875rem;opacity:.85;max-width:36rem">
            Todo lo que veas, crees o cambies aquí es de esta tienda. Para trabajar en otra, cámbiala arriba a la izquierda, en «Tienda».
        </div>
        <a href="{{ \App\Storefront\Urls::home($store) }}" target="_blank" rel="noopener"
            style="display:inline-flex;align-items:center;gap:.4rem;padding:.6rem 1rem;border-radius:.5rem;background:rgb(217 119 6);color:#fff;font-weight:700;text-decoration:none;white-space:nowrap">
            Ver tienda <span aria-hidden="true">↗</span>
        </a>
    @else
        <div style="font-size:1.25rem;font-weight:700">Todavía no hay ninguna tienda: créala en <em>Tiendas</em> para empezar con su catálogo.</div>
    @endif
</div>
