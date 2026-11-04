<?php

namespace App\Modules\Catalog\Infrastructure\Models;

use App\Modules\Catalog\Contracts\Events\VariantCreated;
use App\Modules\Catalog\Database\Factories\ProductVariantFactory;
use App\Modules\Catalog\Domain\ReferencePrice;
use App\Modules\Catalog\Domain\SizeScale;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $product_id
 * @property string $sku
 * @property ?string $size
 * @property ?string $color
 * @property ?int $price_cents
 * @property int $position
 * @property-read Product $product
 */
#[UseFactory(ProductVariantFactory::class)]
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $attributes = [
        'position' => 0,
        'active' => true,
    ];

    protected function casts(): array
    {
        return ['price_cents' => 'integer', 'position' => 'integer', 'active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (ProductVariant $variant): void {
            $variant->sku = Str::upper(trim($variant->sku));
            $variant->size = SizeScale::normalize($variant->size);
        });
        static::created(fn (ProductVariant $variant) => VariantCreated::dispatch($variant->id));
        static::saved(fn (ProductVariant $variant) => $variant->recordPrice(
            $variant->price_cents ?? (int) Product::query()->whereKey($variant->product_id)->value('price_cents'),
        ));
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<VariantPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(VariantPrice::class);
    }

    /** Anota el precio vigente si ha cambiado (historial para las rebajas). */
    public function recordPrice(int $cents): void
    {
        $last = $this->prices()->latest('started_at')->latest('id')->value('price_cents');
        if ($last === null || (int) $last !== $cents) {
            $this->prices()->create(['price_cents' => $cents, 'started_at' => now()]);
        }
    }

    /**
     * Precio anterior y % de descuento si el precio actual es una rebaja legal (30 días).
     *
     * @return array{cents: int, percent: int}|null
     */
    public function discount(): ?array
    {
        return ReferencePrice::discount($this->prices()
            ->get(['price_cents', 'started_at'])
            ->map(fn (VariantPrice $p): array => ['cents' => $p->price_cents, 'since' => $p->started_at->getTimestamp()])
            ->all());
    }

    public function price(): Money
    {
        return Money::fromCents($this->price_cents ?? $this->product->price_cents);
    }

    public function label(): string
    {
        return implode(' · ', array_filter([$this->size, $this->color]));
    }
}
