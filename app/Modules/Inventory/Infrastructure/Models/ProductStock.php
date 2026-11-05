<?php

namespace App\Modules\Inventory\Infrastructure\Models;

use App\Modules\Inventory\Contracts\StockStatus;
use App\Modules\Inventory\Domain\StockPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Inventario de un producto («Inventario» de la ficha, como en WooCommerce):
 * estado (Hay existencias / Agotado) y, si se gestiona, la cantidad que queda para
 * vender entre todas sus tallas y colores. Sin fila = hay existencias, sin límite.
 *
 * @property int $id
 * @property int $product_id
 * @property StockStatus $status
 * @property bool $managed
 * @property int $quantity
 * @property ?int $low_stock_threshold
 */
class ProductStock extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['status' => 'in_stock', 'managed' => false, 'quantity' => 0];

    protected function casts(): array
    {
        return ['status' => StockStatus::class, 'managed' => 'boolean', 'quantity' => 'integer', 'low_stock_threshold' => 'integer'];
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'product_id', 'product_id');
    }

    /** Unidades que se pueden vender: null = sin límite; 0 = agotado. */
    public function limit(): ?int
    {
        return StockPolicy::limit($this->status, $this->managed, $this->quantity);
    }

    public function isLow(): bool
    {
        return StockPolicy::isLow($this->managed, $this->quantity, $this->low_stock_threshold);
    }
}
