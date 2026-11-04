<?php

namespace App\Modules\Catalog\Infrastructure\Models;

use App\Modules\Catalog\Domain\SizeScale;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fila de la guía de tallas de una prenda base (medidas en cm).
 *
 * @property int $id
 * @property int $base_garment_id
 * @property string $size
 * @property ?string $chest_cm
 * @property ?string $length_cm
 * @property ?string $sleeve_cm
 */
class GarmentSize extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['position' => 0];

    protected function casts(): array
    {
        return ['chest_cm' => 'decimal:1', 'length_cm' => 'decimal:1', 'sleeve_cm' => 'decimal:1', 'position' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(fn (GarmentSize $row) => $row->size = SizeScale::normalize($row->size) ?? 'Única');
    }

    /**
     * @return BelongsTo<BaseGarment, $this>
     */
    public function garment(): BelongsTo
    {
        return $this->belongsTo(BaseGarment::class, 'base_garment_id');
    }
}
