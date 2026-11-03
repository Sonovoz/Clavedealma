<?php

use App\Filament\Resources\Stores\Pages\CreateStore;
use App\Filament\Resources\Stores\Pages\ListStores;
use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Stores\Contracts\StoreStatus;
use App\Modules\Stores\Infrastructure\Models\Artist;
use App\Modules\Stores\Infrastructure\Models\Store;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->withRole(Role::Superadmin)->create());
});

it('crea una tienda guardando la comisión en puntos básicos y el subdominio normalizado', function () {
    $artist = Artist::factory()->create();

    Livewire::test(CreateStore::class)
        ->fillForm([
            'artist_id' => $artist->id,
            'name' => 'Luna Bruma',
            'subdomain' => 'LunaBruma',
            'status' => StoreStatus::Published->value,
            'commission_bps' => '27,5',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $store = Store::sole();
    expect($store->subdomain)->toBe('lunabruma')
        ->and($store->commission_bps)->toBe(2750)
        ->and($store->status)->toBe(StoreStatus::Published)
        ->and($store->published_at)->not->toBeNull();
});

it('no admite subdominios reservados ni repetidos', function () {
    $artist = Artist::factory()->create();
    Store::factory()->for($artist)->create(['subdomain' => 'lunabruma']);

    Livewire::test(CreateStore::class)
        ->fillForm(['artist_id' => $artist->id, 'name' => 'X', 'subdomain' => 'admin', 'commission_bps' => '25'])
        ->call('create')
        ->assertHasFormErrors(['subdomain']);

    Livewire::test(CreateStore::class)
        ->fillForm(['artist_id' => $artist->id, 'name' => 'X', 'subdomain' => 'lunabruma', 'commission_bps' => '25'])
        ->call('create')
        ->assertHasFormErrors(['subdomain']);
});

it('lista las tiendas', function () {
    $stores = Store::factory()->count(3)->create();

    Livewire::test(ListStores::class)->assertCanSeeTableRecords($stores);
});

it('solo el superadministrador gestiona tiendas, artistas y catálogo', function (Role $role) {
    $this->actingAs(User::factory()->withRole($role)->create());

    foreach (['/admin/tiendas', '/admin/artistas', '/admin/productos', '/admin/colecciones', '/admin/prendas-base'] as $url) {
        $this->get($url)->assertForbidden();
    }
})->with([Role::Manufacturer, Role::Artist]);

it('cifra el IBAN del artista en la base de datos', function () {
    $artist = Artist::factory()->create(['iban' => 'ES9121000418450200051332']);

    $raw = DB::table('artists')->where('id', $artist->id)->value('iban');
    expect($raw)->not->toContain('ES91')
        ->and($artist->fresh()->iban)->toBe('ES9121000418450200051332');
});
