<?php

namespace App\Modules\Catalog\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $path
 * @property ?string $color
 * @property bool $is_swatch
 */
class ProductImage extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['is_swatch' => false, 'position' => 0];

    protected function casts(): array
    {
        return ['is_swatch' => 'boolean', 'position' => 'integer'];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
