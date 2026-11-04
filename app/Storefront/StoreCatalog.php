<?php

namespace App\Storefront;

use App\Modules\Catalog\Contracts\ProductStatus;
use App\Modules\Catalog\Domain\SizeScale;
use App\Modules\Catalog\Infrastructure\Models\BaseGarment;
use App\Modules\Catalog\Infrastructure\Models\Collection;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Catalog\Infrastructure\Models\ProductImage;
use App\Modules\Catalog\Infrastructure\Models\ProductVariant;
use App\Modules\Inventory\Contracts\StockMode;
use App\Modules\Inventory\Infrastructure\Models\StockLevel;
use App\Modules\Stores\Infrastructure\Models\Store;
use App\Support\Money;
use Illuminate\Support\Collection as LaravelCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Lectura del catálogo para la pasarela de productos: solo artículos publicados
 * (el equipo puede previsualizar la ficha de un borrador). Devuelve datos listos
 * para pintar; ninguna plantilla calcula precios ni rebajas por su cuenta.
 *
 * @phpstan-type Card array{name: string, reference: ?string, url: string, image: ?string, hover: ?string, price: string, discount: ?array{before: string, percent: int}, colors: list<array{name: string, hex: ?string, swatch: ?string}>, collections: list<string>, garment: ?string, type: string}
 */
class StoreCatalog
{
    /**
     * Colecciones con al menos un artículo publicado, en su orden.
     *
     * @return LaravelCollection<int, Collection>
     */
    public function collections(Store $store): LaravelCollection
    {
        return Collection::query()
            ->where('store_id', $store->id)
            ->whereHas('products', fn ($q) => $q->where('status', ProductStatus::Published))
            ->orderBy('position')
            ->get();
    }

    /**
     * @return list<Card>
     */
    public function cards(Store $store, ?Collection $collection = null): array
    {
        $products = Product::query()
            ->where('store_id', $store->id)
            ->where('status', ProductStatus::Published)
            ->when($collection, fn ($q) => $q->whereHas('collections', fn ($c) => $c->whereKey($collection?->id)))
            ->with([
                'images',
                'variants' => fn ($q) => $q->where('active', true),
                'collections' => fn ($q) => $q->orderBy('position'),
                'baseGarment',
            ])
            ->get();

        $order = fn (Product $p): array => [
            (int) ($p->collections->min('position') ?? 999),
            (int) ($p->collections->first()?->getAttribute('pivot')?->getAttribute('position') ?? 999),
            $p->id,
        ];

        return $products
            ->sortBy(fn (Product $p): array => $order($p))
            ->values()
            ->map(fn (Product $p): array => $this->card($store, $p))
            ->all();
    }

    /**
     * @return Card
     */
    private function card(Store $store, Product $product): array
    {
        $photos = $product->images->where('is_swatch', false)->values();
        $first = $product->variants->first(fn (ProductVariant $v): bool => $v->price_cents === null) ?? $product->variants->first();
        $discount = $first?->discount();

        return [
            'name' => $product->name,
            'reference' => $product->reference,
            'url' => Urls::product($store, $product->slug),
            'image' => $this->url($photos->get(0)?->path),
            'hover' => $this->url($photos->get(1)?->path),
            'price' => Money::fromCents($product->price_cents)->format(),
            'discount' => $discount === null ? null : ['before' => Money::fromCents($discount['cents'])->format(), 'percent' => $discount['percent']],
            'colors' => $this->colors($product),
            'collections' => $product->collections->pluck('slug')->all(),
            'garment' => $product->baseGarment?->name,
            // Tipo de prenda para el filtro: primera palabra de la prenda base (camiseta, sudadera, gorra…).
            'type' => $product->baseGarment === null ? 'otros' : Str::slug(Str::before($product->baseGarment->name.' ', ' ')),
        ];
    }

    /**
     * Ficha completa de un artículo, o null si no existe o no se puede ver.
     *
     * @return array<string, mixed>|null
     */
    public function product(Store $store, string $slug, bool $staff): ?array
    {
        $product = Product::query()
            ->where('store_id', $store->id)
            ->where('slug', $slug)
            ->when(! $staff, fn ($q) => $q->where('status', ProductStatus::Published))
            ->with(['images', 'variants' => fn ($q) => $q->where('active', true), 'collections'])
            ->first();
        if ($product === null) {
            return null;
        }

        $garment = $product->base_garment_id === null ? null : BaseGarment::query()->with('sizes')->find($product->base_garment_id);
        $stock = StockLevel::query()->whereIn('product_variant_id', $product->variants->pluck('id'))->get()->keyBy('product_variant_id');

        $variants = $product->variants->map(function (ProductVariant $v) use ($product, $stock): array {
            $cents = $v->price_cents ?? $product->price_cents;
            $discount = $v->discount();
            $level = $stock->get($v->id);
            $soldOut = $level !== null && $level->mode === StockMode::LimitedThenSoldOut && $level->quantity <= 0;

            return [
                'id' => $v->id,
                'sku' => $v->sku,
                'size' => $v->size,
                'color' => $v->color,
                'price' => Money::fromCents($cents)->format(),
                'before' => $discount === null ? null : Money::fromCents($discount['cents'])->format(),
                'percent' => $discount['percent'] ?? null,
                'available' => ! $soldOut,
                'limited' => $level !== null && $level->mode !== StockMode::OnDemand && ! $soldOut ? $level->quantity : null,
            ];
        })->values()->all();

        $sizes = SizeScale::sort(array_values(array_unique(array_map(fn (array $v): ?string => $v['size'], $variants))));
        $first = $variants[0] ?? null;

        return [
            'model' => $product,
            'name' => $product->name,
            'reference' => $product->reference,
            'status' => $product->status,
            'description' => $product->description === null ? null : Str::sanitizeHtml($product->description),
            'model_info' => $product->model_info,
            'lead_time' => $product->leadTime()->label(),
            'personalized' => (bool) $product->getAttribute('personalized'),
            'price' => $first['price'] ?? Money::fromCents($product->price_cents)->format(),
            'before' => $first['before'] ?? null,
            'percent' => $first['percent'] ?? null,
            'variants' => $variants,
            'sizes' => array_values(array_filter($sizes, fn (?string $s): bool => $s !== null)),
            'colors' => $this->colors($product),
            'images' => $product->images->where('is_swatch', false)->map(fn (ProductImage $i): array => [
                'url' => $this->url($i->path),
                'alt' => $i->getAttribute('alt') ?: $product->name,
                'color' => $i->color,
            ])->values()->all(),
            'collections' => $product->collections->map(fn (Collection $c): array => ['name' => $c->name, 'url' => Urls::collection($store, $c->slug)])->all(),
            'garment' => $garment === null ? null : [
                'name' => $garment->name,
                'material' => $garment->getAttribute('material'),
                'composition' => $garment->composition,
                'fit' => $garment->fit,
                'care' => $garment->care,
                'origin' => $garment->origin_country,
                'note' => $garment->size_guide_note,
                'sizes' => $garment->sizes->map(fn ($s): array => [
                    'size' => $s->size,
                    'chest' => $this->cm($s->chest_cm),
                    'length' => $this->cm($s->length_cm),
                    'sleeve' => $this->cm($s->sleeve_cm),
                ])->all(),
            ],
        ];
    }

    /**
     * Colores del artículo en su orden, con la foto de muestra de tejido si la hay.
     *
     * @return list<array{name: string, hex: ?string, swatch: ?string}>
     */
    private function colors(Product $product): array
    {
        $colors = [];
        foreach ($product->variants as $variant) {
            if ($variant->color === null || isset($colors[Str::lower($variant->color)])) {
                continue;
            }
            $swatch = $product->images->first(fn (ProductImage $i): bool => $i->is_swatch && Str::lower((string) $i->color) === Str::lower((string) $variant->color));
            $colors[Str::lower($variant->color)] = [
                'name' => $variant->color,
                'hex' => $variant->getAttribute('color_hex'),
                'swatch' => $this->url($swatch?->path),
            ];
        }

        return array_values($colors);
    }

    private function url(?string $path): ?string
    {
        return $path === null ? null : Storage::disk('public')->url($path);
    }

    private function cm(?string $value): ?string
    {
        return $value === null ? null : rtrim(rtrim(str_replace('.', ',', $value), '0'), ',');
    }
}
