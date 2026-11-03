<?php

use App\Modules\Catalog\Infrastructure\Models\Collection;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Catalog\Infrastructure\Models\ProductVariant;
use App\Modules\Inventory\Contracts\StockMode;
use App\Modules\Inventory\Infrastructure\Models\StockLevel;
use App\Modules\Stores\Contracts\StoreStatus;
use App\Modules\Stores\Infrastructure\Models\Store;
use Database\Seeders\FarruquitoSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('public'));

it('crea la tienda Farruquito oculta con sus tres colecciones y 35 piezas', function () {
    $this->seed(FarruquitoSeeder::class);

    $store = Store::where('subdomain', 'farruquito')->sole();
    expect($store->status)->toBe(StoreStatus::Hidden)
        ->and($store->artist->display_name)->toBe('Farruquito');

    $colecciones = Collection::where('store_id', $store->id)->withCount('products')->orderBy('position')->get();
    expect($colecciones->pluck('name')->all())->toBe(['On Stage', 'Artist Voice', 'Iconic'])
        ->and($colecciones->pluck('products_count')->all())->toBe([12, 12, 11])
        ->and(Product::where('store_id', $store->id)->count())->toBe(35);
});

it('da a cada variante su ficha de stock y limita la edición On Stage', function () {
    $this->seed(FarruquitoSeeder::class);

    expect(StockLevel::count())->toBe(ProductVariant::count());

    $onStage = Collection::where('slug', 'on-stage')->sole()->products()->pluck('products.id');
    $limitadas = StockLevel::whereIn('product_variant_id', ProductVariant::whereIn('product_id', $onStage)->pluck('id'))->get();
    expect($limitadas)->each(fn ($level) => $level->mode->toBe(StockMode::LimitedThenSoldOut)->quantity->toBe(25));
});

it('se puede ejecutar dos veces sin duplicar nada', function () {
    $this->seed(FarruquitoSeeder::class);
    $antes = [Product::count(), ProductVariant::count(), StockLevel::count()];

    $this->seed(FarruquitoSeeder::class);

    expect([Product::count(), ProductVariant::count(), StockLevel::count()])->toBe($antes);
});
