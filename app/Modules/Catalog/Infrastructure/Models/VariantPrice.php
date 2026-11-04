<?php

namespace App\Modules\Catalog\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Precio que tuvo una variante desde una fecha. Inmutable: cada cambio es una fila nueva.
 *
 * @property int $id
 * @property int $product_variant_id
 * @property int $price_cents
 * @property Carbon $started_at
 */
class VariantPrice extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['price_cents' => 'integer', 'started_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('El historial de precios no se modifica.'));
        static::deleting(fn () => throw new LogicException('El historial de precios no se borra.'));
    }
}
