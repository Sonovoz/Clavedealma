<?php

namespace App\Modules\Catalog\Infrastructure\Models;

use App\Modules\Catalog\Contracts\ProductStatus;
use App\Modules\Catalog\Database\Factories\ProductFactory;
use App\Modules\Catalog\Domain\LeadTime;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Producto de una tienda. La tienda se referencia por `store_id`
 * (módulo Stores); Catálogo no depende de sus clases internas.
 *
 * @property int $id
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property int $store_id
 * @property ?int $base_garment_id
 * @property ?string $reference
 * @property ?string $model_info
 * @property ?string $seo_title
 * @property ?string $seo_description
 * @property string $name
 * @property string $slug
 * @property ?string $description
 * @property int $price_cents
 * @property int $vat_rate_bps
 * @property ?int $commission_bps
 * @property int $lead_time_min_days
 * @property int $lead_time_max_days
 * @property ProductStatus $status
 * @property ?Carbon $published_at
 * @property ?Carbon $deleted_at
 */
#[UseFactory(ProductFactory::class)]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    /** Valores por defecto (iguales a los de la migración). */
    protected $attributes = [
        'vat_rate_bps' => 2100,
        'lead_time_min_days' => 5,
        'lead_time_max_days' => 7,
        'personalized' => false,
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'price_cents' => 'integer',
            'vat_rate_bps' => 'integer',
            'commission_bps' => 'integer',
            'lead_time_min_days' => 'integer',
            'lead_time_max_days' => 'integer',
            'personalized' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Product $product): void {
            $product->slug = Str::slug($product->slug ?: $product->name);
            new LeadTime($product->lead_time_min_days, $product->lead_time_max_days); // valida

            if ($product->status === ProductStatus::Published && $product->published_at === null) {
                $product->published_at = now();
            }
        });

        // Las variantes sin precio propio cambian de precio con el producto.
        static::saved(function (Product $product): void {
            if ($product->wasChanged('price_cents')) {
                $product->variants()->whereNull('price_cents')->get()->each->recordPrice($product->price_cents);
            }
        });
    }

    /**
     * @return BelongsTo<BaseGarment, $this>
     */
    public function baseGarment(): BelongsTo
    {
        return $this->belongsTo(BaseGarment::class);
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('position');
    }

    /**
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    /**
     * @return BelongsToMany<Collection, $this>
     */
    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class)->withPivot('position');
    }

    public function price(): Money
    {
        return Money::fromCents($this->price_cents);
    }

    public function leadTime(): LeadTime
    {
        return new LeadTime($this->lead_time_min_days, $this->lead_time_max_days);
    }
}
