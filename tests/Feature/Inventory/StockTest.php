<?php

use App\Filament\Resources\StockLevels\Pages\ListStockLevels;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Inventory\Application\AdjustStock;
use App\Modules\Inventory\Contracts\StockMode;
use App\Modules\Inventory\Contracts\StockMovementReason;
use App\Modules\Inventory\Infrastructure\Models\StockLevel;
use App\Modules\Inventory\Infrastructure\Models\StockMovement;
use App\Modules\Stores\Infrastructure\Models\Store;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $product = Product::factory()->create(['store_id' => Store::factory()->create()->id]);
    $this->variant = $product->variants()->create(['sku' => 'SKU-1', 'size' => 'M']);
    $this->level = StockLevel::where('product_variant_id', $this->variant->id)->sole();
});

it('ajusta unidades y deja constancia del movimiento', function () {
    $level = app(AdjustStock::class)($this->variant->id, 5, StockMovementReason::Production, 'Tirada inicial');
    $level = app(AdjustStock::class)($this->variant->id, -2, StockMovementReason::Adjustment);

    expect($level->quantity)->toBe(3)
        ->and(StockMovement::pluck('quantity_after')->all())->toBe([5, 3]);
});

it('no deja el stock en negativo', function () {
    app(AdjustStock::class)($this->variant->id, -1, StockMovementReason::Adjustment);
})->throws(InvalidArgumentException::class, 'No hay unidades suficientes');

it('los movimientos de stock no se pueden modificar ni borrar', function () {
    app(AdjustStock::class)($this->variant->id, 1, StockMovementReason::Production);
    $movement = StockMovement::sole();

    expect(fn () => $movement->update(['delta' => 100]))->toThrow(LogicException::class)
        ->and(fn () => $movement->delete())->toThrow(LogicException::class);
});

it('el fabricante ve el stock y ajusta unidades desde el panel', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->withRole(Role::Manufacturer)->create());
    $this->level->update(['mode' => StockMode::LimitedThenSoldOut]);

    $this->get('/admin/stock')->assertOk();

    Livewire::test(ListStockLevels::class)
        ->assertCanSeeTableRecords([$this->level])
        ->assertActionVisible(TestAction::make('adjust')->table($this->level))
        ->callAction(TestAction::make('adjust')->table($this->level), data: [
            'delta' => 4,
            'reason' => StockMovementReason::Production->value,
        ]);

    expect($this->level->fresh()->quantity)->toBe(4)
        ->and(StockMovement::sole()->user_id)->toBe(auth()->id());
});

it('el artista no ve el stock', function () {
    $this->actingAs(User::factory()->withRole(Role::Artist)->create());

    $this->get('/admin/stock')->assertForbidden();
});

it('no ofrece ajustar unidades en variantes bajo pedido', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->withRole(Role::Manufacturer)->create());

    Livewire::test(ListStockLevels::class)
        ->assertActionHidden(TestAction::make('adjust')->table($this->level));
});
