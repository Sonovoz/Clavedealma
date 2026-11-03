<?php

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Modules\Catalog\Contracts\ProductStatus;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Inventory\Contracts\StockMode;
use App\Modules\Inventory\Infrastructure\Models\StockLevel;
use App\Modules\Stores\Infrastructure\Models\Store;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->withRole(Role::Superadmin)->create());
});

it('crea un producto con el precio en céntimos y slug automático', function () {
    $store = Store::factory()->create();

    Livewire::test(CreateProduct::class)
        ->fillForm([
            'store_id' => $store->id,
            'name' => 'Sudadera Gira 2026',
            'price_cents' => '49,90',
            'vat_rate_bps' => '21',
            'status' => ProductStatus::Draft->value,
            'lead_time_min_days' => 5,
            'lead_time_max_days' => 7,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $product = Product::sole();
    expect($product->price_cents)->toBe(4990)
        ->and($product->vat_rate_bps)->toBe(2100)
        ->and($product->commission_bps)->toBeNull()
        ->and($product->slug)->toBe('sudadera-gira-2026');
});

it('rechaza un plazo máximo menor que el mínimo', function () {
    Livewire::test(CreateProduct::class)
        ->fillForm([
            'store_id' => Store::factory()->create()->id,
            'name' => 'X',
            'price_cents' => '10',
            'vat_rate_bps' => '21',
            'lead_time_min_days' => 7,
            'lead_time_max_days' => 3,
        ])
        ->call('create')
        ->assertHasFormErrors(['lead_time_max_days']);
});

it('cada variante nueva nace con su ficha de stock bajo pedido', function () {
    $product = Product::factory()->create(['store_id' => Store::factory()->create()->id]);

    $variant = $product->variants()->create(['sku' => 'test-m-negro', 'size' => 'M', 'color' => 'Negro']);

    $level = StockLevel::where('product_variant_id', $variant->id)->sole();
    expect($level->mode)->toBe(StockMode::OnDemand)
        ->and($level->quantity)->toBe(0)
        ->and($variant->sku)->toBe('TEST-M-NEGRO')
        ->and($variant->price()->cents)->toBe($product->price_cents);
});

it('la comisión propia del producto sustituye a la de la tienda solo si se rellena', function () {
    $store = Store::factory()->create(['commission_bps' => 2500]);
    $product = Product::factory()->create(['store_id' => $store->id, 'commission_bps' => 3000]);

    expect($product->commission_bps)->toBe(3000);
});
