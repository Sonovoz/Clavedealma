<?php

namespace App\Modules\Catalog\Infrastructure\Models;

use App\Modules\Catalog\Database\Factories\BaseGarmentFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property ?int $store_id
 * @property string $code
 * @property string $name
 * @property ?string $composition
 * @property ?string $fit
 * @property ?string $care
 * @property ?string $origin_country
 * @property ?string $size_guide_note
 */
#[UseFactory(BaseGarmentFactory::class)]
class BaseGarment extends Model
{
    /** @use HasFactory<BaseGarmentFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'weight_grams' => 'integer'];
    }

    /**
     * Guía de tallas, en el orden de la escala.
     *
     * @return HasMany<GarmentSize, $this>
     */
    public function sizes(): HasMany
    {
        return $this->hasMany(GarmentSize::class)->orderBy('position');
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
