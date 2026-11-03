<?php

namespace App\Modules\Catalog\Infrastructure\Models;

use App\Modules\Catalog\Contracts\Events\VariantCreated;
use App\Modules\Catalog\Database\Factories\ProductVariantFactory;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $product_id
 * @property string $sku
 * @property ?string $size
 * @property ?string $color
 * @property ?int $price_cents
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
        static::saving(fn (ProductVariant $variant) => $variant->sku = Str::upper(trim($variant->sku)));
        static::created(fn (ProductVariant $variant) => VariantCreated::dispatch($variant->id));
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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
