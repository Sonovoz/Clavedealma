<?php

namespace App\Modules\Catalog\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Colección dentro de una tienda (p. ej. "Gira 2026").
 *
 * @property int $id
 * @property ?Carbon $updated_at
 * @property int $store_id
 * @property string $name
 * @property string $slug
 * @property ?string $description
 * @property ?string $seo_title
 * @property ?string $seo_description
 */
class Collection extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::saving(fn (Collection $collection) => $collection->slug = Str::slug($collection->slug ?: $collection->name));
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withPivot('position')->orderByPivot('position');
    }
}
