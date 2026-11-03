<?php

namespace App\Modules\Inventory\Infrastructure\Models;

use App\Modules\Inventory\Contracts\StockMode;
use App\Modules\Inventory\Domain\StockPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $product_variant_id
 * @property StockMode $mode
 * @property int $quantity
 * @property ?int $low_stock_threshold
 */
class StockLevel extends Model
{
    protected $guarded = ['id'];

    protected $attributes = [
        'mode' => 'on_demand',
        'quantity' => 0,
    ];

    protected function casts(): array
    {
        return [
            'mode' => StockMode::class,
            'quantity' => 'integer',
            'low_stock_threshold' => 'integer',
        ];
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'product_variant_id', 'product_variant_id')->latest('id');
    }

    public function isLow(): bool
    {
        return StockPolicy::isLow($this->mode, $this->quantity, $this->low_stock_threshold);
    }
}
