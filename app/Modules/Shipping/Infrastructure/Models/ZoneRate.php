<?php

namespace App\Modules\Shipping\Infrastructure\Models;

use App\Modules\Shipping\Contracts\ShippingZone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

/**
 * Tarifa de un destino: transportista elegido, baremo por peso, envío gratis y plazo.
 *
 * @property int $id
 * @property ShippingZone $zone
 * @property ?int $carrier_id
 * @property ?int $free_from_cents
 * @property int $min_days
 * @property int $max_days
 * @property bool $active
 * @property-read ?Carrier $carrier
 */
class ZoneRate extends Model
{
    protected $table = 'shipping_zone_rates';

    protected $guarded = ['id'];

    protected $attributes = ['min_days' => 1, 'max_days' => 3, 'active' => false];

    protected function casts(): array
    {
        return [
            'zone' => ShippingZone::class,
            'free_from_cents' => 'integer',
            'min_days' => 'integer',
            'max_days' => 'integer',
            'active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ZoneRate $rate): void {
            if ($rate->max_days < $rate->min_days) {
                throw new InvalidArgumentException('El plazo máximo no puede ser menor que el mínimo.');
            }
        });
    }

    /**
     * @return BelongsTo<Carrier, $this>
     */
    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class);
    }

    /**
     * @return HasMany<RateBand, $this>
     */
    public function bands(): HasMany
    {
        return $this->hasMany(RateBand::class, 'shipping_zone_rate_id')->orderBy('max_weight_grams');
    }
}
