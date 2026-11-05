<?php

namespace App\Modules\Inventory\Application;

use App\Modules\Inventory\Contracts\Events\StockShortage;
use App\Modules\Inventory\Contracts\StockMovementReason;
use App\Modules\Inventory\Domain\StockPolicy;
use App\Modules\Inventory\Infrastructure\Models\ProductStock;
use App\Modules\Inventory\Infrastructure\Models\StockMovement;
use App\Modules\Inventory\Infrastructure\ProductsOfVariants;
use App\Modules\Orders\Contracts\Events\OrderPaid;
use Illuminate\Support\Facades\DB;

/**
 * Pedido pagado → si el producto gestiona inventario, se descuentan las unidades.
 * Si se pagaron más de las que quedaban (dos clientes a la vez), avisa (StockShortage).
 * Los productos sin límite se fabrican bajo pedido y no mueven nada.
 */
class RecordSale
{
    public function __construct(private readonly ProductsOfVariants $products) {}

    public function handle(OrderPaid $event): void
    {
        foreach ($this->products->units($event->lines) as $productId => $requested) {
            $missing = DB::transaction(function () use ($event, $productId, $requested): int {
                $stock = ProductStock::query()->where('product_id', $productId)->lockForUpdate()->first();
                if ($stock === null || ! $stock->managed) {
                    return 0;
                }

                $taken = StockPolicy::taken(true, $stock->quantity, $requested);
                if ($taken > 0) {
                    $stock->quantity -= $taken;
                    $stock->save();
                    StockMovement::query()->create([
                        'product_id' => $productId,
                        'delta' => -$taken,
                        'quantity_after' => $stock->quantity,
                        'reason' => StockMovementReason::Sale,
                        'note' => 'Pedido '.$event->reference,
                    ]);
                }

                return $requested - $taken;
            });

            if ($missing > 0) {
                StockShortage::dispatch($event->orderId, $productId, $missing);
            }
        }
    }
}
