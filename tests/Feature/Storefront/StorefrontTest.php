<?php

use App\Modules\Catalog\Contracts\ProductStatus;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Stores\Contracts\StoreStatus;
use App\Modules\Stores\Domain\Subdomain;
use App\Modules\Stores\Infrastructure\Models\Store;
use App\Storefront\Urls;
use Database\Seeders\FarruquitoSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->withoutVite();
});

function tiendaPublicada(array $attrs = []): Store
{
    $store = Store::factory()->create(array_merge(['subdomain' => 'prueba', 'name' => 'Tienda Prueba', 'status' => StoreStatus::Published], $attrs));
    $product = Product::factory()->create(['store_id' => $store->id, 'name' => 'Camiseta gira', 'reference' => 'PR-01', 'status' => ProductStatus::Published, 'price_cents' => 2990]);
    $product->variants()->create(['sku' => 'PR-01-M-NEGRO', 'size' => 'M', 'color' => 'Negro', 'color_hex' => '#111111']);
    $product->variants()->create(['sku' => 'PR-01-3XL-NEGRO', 'size' => '3XL', 'color' => 'Negro', 'price_cents' => 3290]);
    Product::factory()->create(['store_id' => $store->id, 'name' => 'Borrador secreto', 'status' => ProductStatus::Draft]);

    return $store;
}

it('enseña la tienda publicada con su pasarela en clavedealma.es/<clave>', function () {
    $store = tiendaPublicada();

    expect(Urls::home($store))->toBe(rtrim(config('app.url'), '/').'/prueba');
    $this->get('/prueba')->assertOk()->assertSee('Camiseta gira')->assertSee('29,90')->assertDontSee('Borrador secreto');
    $this->get('/')->assertOk()->assertSee('Tienda Prueba');
});

it('el subdominio y /tienda/<clave> redirigen (301) a la dirección principal, conservando la página', function () {
    tiendaPublicada();
    $base = rtrim(config('app.url'), '/');

    $this->get('http://prueba.'.config('clavedealma.domain').'/')->assertStatus(301)->assertRedirect($base.'/prueba');
    $this->get('http://prueba.'.config('clavedealma.domain').'/p/camiseta-gira?color=negro')->assertStatus(301)->assertRedirect($base.'/prueba/p/camiseta-gira?color=negro');
    $this->get($base.'/tienda/prueba')->assertStatus(301)->assertRedirect($base.'/prueba');
    $this->get($base.'/tienda/prueba/coleccion/gira')->assertStatus(301)->assertRedirect($base.'/prueba/coleccion/gira');
});

it('las palabras reservadas nunca son una tienda', function () {
    expect(Subdomain::isValid('admin'))->toBeFalse()
        ->and(Subdomain::isValid('carrito'))->toBeFalse()
        ->and(Subdomain::isValid('livewire'))->toBeFalse()
        ->and(Subdomain::isValid('farruquito'))->toBeTrue()
        ->and(Subdomain::isValid('admin-flamenco'))->toBeTrue();

    expect(preg_match('#^'.Subdomain::routePattern().'$#', 'admin'))->toBe(0)
        ->and(preg_match('#^'.Subdomain::routePattern().'$#', 'admin-flamenco'))->toBe(1)
        ->and(preg_match('#^'.Subdomain::routePattern().'$#', 'farruquito'))->toBe(1);

    // /admin sigue siendo el panel, no una tienda.
    $this->get('/admin')->assertRedirect();
    $this->get('/sitemap.xml')->assertOk();
});

it('la ficha muestra tallas en orden, color en el título y precio propio de la talla grande', function () {
    $store = tiendaPublicada();
    $slug = Product::where('reference', 'PR-01')->value('slug');

    $this->get(Urls::product($store, $slug))
        ->assertOk()
        ->assertSeeInOrder(['Camiseta gira | NEGRO', 'Ref. PR-01', 'M', '3XL'])
        ->assertSee('32,90'); // en los datos de la variante 3XL
});

it('una tienda oculta solo la ve el equipo de catálogo', function () {
    tiendaPublicada(['status' => StoreStatus::Hidden]);

    $this->get('/prueba')->assertNotFound();
    $this->get('/')->assertDontSee('Tienda Prueba');

    $this->actingAs(User::factory()->withRole(Role::Manufacturer)->create())->get('/prueba')->assertNotFound();
    $this->actingAs(User::factory()->withRole(Role::CatalogManager)->create())->get('/prueba')->assertOk()->assertSee('Vista previa para el equipo');
});

it('el equipo puede previsualizar la ficha de un borrador; el público no', function () {
    $store = tiendaPublicada();
    $draft = Product::where('status', ProductStatus::Draft)->sole();

    $this->get(Urls::product($store, $draft->slug))->assertNotFound();
    $this->actingAs(User::factory()->withRole(Role::Superadmin)->create())
        ->get(Urls::product($store, $draft->slug))->assertOk()->assertSee('este artículo está en');
});

it('la tienda Farruquito usa su diseño y sus imágenes no salen mientras está oculta', function () {
    $this->seed(FarruquitoSeeder::class);
    $store = Store::where('subdomain', 'farruquito')->sole();

    expect($store->design)->toBe('farruquito');
    $this->get(Urls::designAsset('farruquito', 'retrato.webp'))->assertNotFound();

    $this->actingAs(User::factory()->withRole(Role::CatalogManager)->create());
    $this->get(Urls::home($store))
        ->assertOk()
        ->assertSee('Tres colecciones, un mismo origen')
        ->assertSee('La esencia')
        ->assertSee(Urls::product($store, Product::where('reference', 'FQ-OS-01')->value('slug')), false);
    $this->get(Urls::designAsset('farruquito', 'retrato.webp'))->assertOk();
    $this->get(Urls::designAsset('farruquito', '../../.env'))->assertNotFound();
    $this->get(Urls::collection($store, 'iconic'))->assertOk()->assertSee('Classic Embroidery');
});

it('la matriz enseña las tiendas: el público solo las publicadas; el equipo también las ocultas', function () {
    $this->seed(FarruquitoSeeder::class);
    tiendaPublicada();

    $this->get('/')
        ->assertOk()
        ->assertSee('Tiendas oficiales')
        ->assertSee('Tienda Prueba')
        ->assertDontSee('Farruquito')
        ->assertDontSee('Piezas con alma'); // el drop de productos inventados del prototipo no está
    $this->get(Urls::designAsset('matriz', 'logo.png'))->assertOk();
    $this->get(Urls::designAsset('farruquito', 'tarjeta.webp'))->assertNotFound();

    $this->actingAs(User::factory()->withRole(Role::CatalogManager)->create())
        ->get('/')
        ->assertSee('Farruquito')
        ->assertSee('Oculta · solo equipo')
        ->assertSee('Tienda oficial · On Stage · Artist Voice · Iconic')
        ->assertSee(Urls::home('farruquito'), false)
        ->assertSee(Urls::designAsset('farruquito', 'tarjeta.webp'), false);
});
