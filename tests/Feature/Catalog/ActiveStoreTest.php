<?php

use App\Filament\Livewire\StoreSwitcher;
use App\Filament\Resources\BaseGarments\Pages\CreateBaseGarment;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Support\ActiveStore;
use App\Modules\Catalog\Infrastructure\Models\BaseGarment;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Stores\Infrastructure\Models\Store;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->withRole(Role::CatalogManager)->create());
    $this->a = Store::factory()->create(['name' => 'A tienda']);
    $this->b = Store::factory()->create(['name' => 'B tienda']);
    $this->pa = Product::factory()->create(['store_id' => $this->a->id, 'name' => 'Pieza de A']);
    $this->pb = Product::factory()->create(['store_id' => $this->b->id, 'name' => 'Pieza de B']);
});

it('el catálogo enseña solo la tienda activa y se cambia con el selector', function () {
    expect(ActiveStore::id())->toBe($this->a->id); // por defecto, la primera por nombre

    Livewire::test(ListProducts::class)->assertCanSeeTableRecords([$this->pa])->assertCanNotSeeTableRecords([$this->pb]);

    Livewire::test(StoreSwitcher::class)->set('storeId', $this->b->id);
    expect(ActiveStore::id())->toBe($this->b->id);

    Livewire::test(ListProducts::class)->assertCanSeeTableRecords([$this->pb])->assertCanNotSeeTableRecords([$this->pa]);
    $this->get(ProductResource::getUrl('edit', ['record' => $this->pa]))->assertNotFound();
});

it('lo que se crea va siempre a la tienda activa', function () {
    ActiveStore::set($this->b->id);

    Livewire::test(CreateBaseGarment::class)
        ->fillForm(['code' => 'CAM-1', 'name' => 'Camiseta', 'composition' => '100 % algodón'])
        ->call('create')
        ->assertHasNoFormErrors();
    $garment = BaseGarment::where('code', 'CAM-1')->sole();
    expect($garment->store_id)->toBe($this->b->id);

    // El mismo código puede existir en otra tienda
    BaseGarment::factory()->create(['store_id' => $this->a->id, 'code' => 'CAM-2']);
    Livewire::test(CreateBaseGarment::class)->fillForm(['code' => 'CAM-2', 'name' => 'Otra'])->call('create')->assertHasNoFormErrors();

    // «Crear artículo con esta prenda» llega con la prenda elegida
    $this->get(ProductResource::getUrl('create', ['prenda' => $garment->id]))->assertOk();
    Livewire::withQueryParams(['prenda' => $garment->id])->test(CreateProduct::class)->assertSchemaStateSet(['base_garment_id' => $garment->id]);

    Livewire::test(CreateProduct::class)
        ->fillForm(['name' => 'Nueva', 'price_cents' => '20', 'vat_rate_bps' => '21', 'lead_time_min_days' => 5, 'lead_time_max_days' => 7, 'base_garment_id' => $garment->id])
        ->call('create')
        ->assertHasNoFormErrors();
    expect(Product::where('name', 'Nueva')->value('store_id'))->toBe($this->b->id);
});
