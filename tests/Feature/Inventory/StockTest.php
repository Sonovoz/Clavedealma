<?php

use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Inventory\Application\AdjustStock;
use App\Modules\Inventory\Application\ConfigureStock;
use App\Modules\Inventory\Contracts\StockAvailability;
use App\Modules\Inventory\Contracts\StockMovementReason;
use App\Modules\Inventory\Contracts\StockStatus;
use App\Modules\Inventory\Infrastructure\Models\ProductStock;
use App\Modules\Inventory\Infrastructure\Models\StockMovement;
use App\Modules\Stores\Infrastructure\Models\Store;

beforeEach(function () {
    $this->product = Product::factory()->create(['store_id' => Store::factory()->create()->id]);
    $this->product->variants()->create(['sku' => 'SKU-M', 'size' => 'M']);
    $this->product->variants()->create(['sku' => 'SKU-L', 'size' => 'L']);
});

it('sin configurar, el producto tiene existencias y no hay límite', function () {
    expect(app(StockAvailability::class)->productLimits([$this->product->id]))->toBe([$this->product->id => null])
        ->and(ProductStock::count())->toBe(0);
});

it('ajusta unidades del producto y deja constancia del movimiento', function () {
    app(AdjustStock::class)($this->product->id, 5, StockMovementReason::Initial, 'Tirada inicial');
    $stock = app(AdjustStock::class)($this->product->id, -2, StockMovementReason::Adjustment);

    expect($stock->quantity)->toBe(3)
        ->and(StockMovement::pluck('quantity_after')->all())->toBe([5, 3]);
});

it('no deja el inventario en negativo', function () {
    app(AdjustStock::class)($this->product->id, -1, StockMovementReason::Adjustment);
})->throws(InvalidArgumentException::class, 'No hay unidades suficientes');

it('los movimientos no se pueden modificar ni borrar', function () {
    app(AdjustStock::class)($this->product->id, 1, StockMovementReason::Production);
    $movement = StockMovement::sole();

    expect(fn () => $movement->update(['delta' => 100]))->toThrow(LogicException::class)
        ->and(fn () => $movement->delete())->toThrow(LogicException::class);
});

it('«Agotado» y «Gestionar inventario» marcan lo que se puede vender', function () {
    $configure = app(ConfigureStock::class);
    $limit = fn (): ?int => app(StockAvailability::class)->productLimits([$this->product->id])[$this->product->id];

    $configure($this->product->id, StockStatus::OutOfStock, false);
    expect($limit())->toBe(0);

    $configure($this->product->id, StockStatus::InStock, true, 12, 3, loadedQuantity: 0);
    expect($limit())->toBe(12)->and(ProductStock::sole()->low_stock_threshold)->toBe(3);

    // La ficha aplica la diferencia sobre lo que había al abrirla: no pisa lo vendido entretanto.
    app(AdjustStock::class)($this->product->id, -2, StockMovementReason::Sale, 'Pedido X');
    $configure($this->product->id, StockStatus::InStock, true, 15, 3, loadedQuantity: 12);
    expect($limit())->toBe(13);

    $configure($this->product->id, StockStatus::InStock, false);
    expect($limit())->toBeNull();
});

it('el importador no pisa un inventario que ya tiene ventas', function () {
    $configure = app(ConfigureStock::class);
    $configure($this->product->id, StockStatus::InStock, true, 10);
    app(AdjustStock::class)($this->product->id, -1, StockMovementReason::Sale, 'Pedido X');

    $warning = $configure($this->product->id, StockStatus::InStock, true, 25);

    expect($warning)->toContain('ya tiene ventas')->and(ProductStock::sole()->quantity)->toBe(9);
});

it('el stock por variante ya no existe: solo inventario por producto', function () {
    expect(Schema::hasTable('stock_levels'))->toBeFalse()
        ->and(Schema::hasTable('product_stocks'))->toBeTrue()
        ->and(Schema::hasTable('product_stock_movements'))->toBeTrue();
});
