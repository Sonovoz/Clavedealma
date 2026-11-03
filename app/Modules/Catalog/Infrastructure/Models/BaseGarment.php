<?php

namespace App\Modules\Catalog\Infrastructure\Models;

use App\Modules\Catalog\Database\Factories\BaseGarmentFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
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
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
