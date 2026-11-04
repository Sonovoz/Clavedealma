<?php

namespace App\Modules\Catalog\Infrastructure\Lookup;

use App\Modules\Catalog\Contracts\ProductStatus;
use App\Modules\Catalog\Contracts\SellableVariant;
use App\Modules\Catalog\Contracts\VariantLookup;
use App\Modules\Catalog\Infrastructure\Models\ProductImage;
use App\Modules\Catalog\Infrastructure\Models\ProductVariant;
use Illuminate\Support\Str;

class EloquentVariantLookup implements VariantLookup
{
    public function find(array $variantIds): array
    {
        if ($variantIds === []) {
            return [];
        }

        $variants = ProductVariant::query()
            ->whereKey(array_values(array_unique($variantIds)))
            ->with(['product' => fn ($q) => $q->withTrashed()->with(['images', 'baseGarment'])])
            ->get();

        $found = [];
        foreach ($variants as $variant) {
            $product = $variant->product;
            $photos = $product->images->where('is_swatch', false);
            $image = $photos->first(fn (ProductImage $i): bool => $variant->color !== null && Str::lower((string) $i->color) === Str::lower($variant->color))
                ?? $photos->first();

            $found[$variant->id] = new SellableVariant(
                variantId: $variant->id,
                productId: $product->id,
                storeId: $product->store_id,
                sku: $variant->sku,
                productName: $product->name,
                productSlug: $product->slug,
                reference: $product->reference,
                size: $variant->size,
                color: $variant->color,
                unitPriceCents: $variant->price_cents ?? $product->price_cents,
                vatRateBps: $product->vat_rate_bps,
                commissionBps: $product->commission_bps,
                weightGrams: $product->baseGarment?->getAttribute('weight_grams'),
                leadTimeMinDays: $product->lead_time_min_days,
                leadTimeMaxDays: $product->lead_time_max_days,
                personalized: (bool) $product->getAttribute('personalized'),
                imagePath: $image?->path,
                onSale: $product->deleted_at === null && $product->status === ProductStatus::Published && (bool) $variant->getAttribute('active'),
            );
        }

        return $found;
    }
}
