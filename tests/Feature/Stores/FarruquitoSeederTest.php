<?php

use App\Modules\Catalog\Infrastructure\Models\Collection;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Catalog\Infrastructure\Models\ProductVariant;
use App\Modules\Inventory\Infrastructure\Models\ProductStock;
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

it('da a cada producto su inventario y limita la edición On Stage', function () {
    $this->seed(FarruquitoSeeder::class);

    expect(ProductStock::count())->toBe(Product::count());

    $onStage = Collection::where('slug', 'on-stage')->sole()->products()->pluck('products.id');
    $limitadas = ProductStock::whereIn('product_id', $onStage)->get();
    expect($limitadas)->not->toBeEmpty()
        ->and($limitadas)->each(fn ($stock) => $stock->managed->toBeTrue()->quantity->toBe(25));
});

it('se puede ejecutar dos veces sin duplicar nada', function () {
    $this->seed(FarruquitoSeeder::class);
    $antes = [Product::count(), ProductVariant::count(), ProductStock::count()];

    $this->seed(FarruquitoSeeder::class);

    expect([Product::count(), ProductVariant::count(), ProductStock::count()])->toBe($antes);
});

it('en el servidor solo se ejecuta en la fase de pruebas, nunca con la web lanzada', function () {
    app()->detectEnvironment(fn (): string => 'production');

    // Directo (sin db:seed, que en producción pide confirmación).
    $seed = fn () => app(FarruquitoSeeder::class)->setContainer(app())->__invoke();

    config()->set('clavedealma.seo.indexing', true);
    $seed();
    expect(Store::query()->where('subdomain', 'farruquito')->exists())->toBeFalse();

    config()->set('clavedealma.seo.indexing', false);
    $seed();
    expect(Store::query()->where('subdomain', 'farruquito')->sole()->status)->toBe(StoreStatus::Hidden);
});
