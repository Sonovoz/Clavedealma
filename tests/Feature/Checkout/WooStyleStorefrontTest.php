<?php

use App\Modules\Catalog\Contracts\ProductStatus;
use App\Modules\Catalog\Infrastructure\Models\Collection;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Inventory\Application\ConfigureStock;
use App\Modules\Inventory\Contracts\StockStatus;
use App\Modules\Stores\Contracts\StoreStatus;
use App\Modules\Stores\Infrastructure\Models\Store;
use App\Storefront\Urls;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->withoutVite();
    $this->store = Store::factory()->create(['subdomain' => 'luna', 'name' => 'Luna Bruma', 'status' => StoreStatus::Published]);
    $this->collection = Collection::create(['store_id' => $this->store->id, 'name' => 'Gira', 'slug' => 'gira', 'position' => 1]);
    $this->a = Product::factory()->create(['store_id' => $this->store->id, 'name' => 'La esencia', 'reference' => 'LB-01', 'status' => ProductStatus::Published, 'price_cents' => 3000]);
    $this->b = Product::factory()->create(['store_id' => $this->store->id, 'name' => 'Otra pieza', 'status' => ProductStatus::Published, 'price_cents' => 2000]);
    $this->a->collections()->attach($this->collection->id, ['position' => 1]);
    $this->b->collections()->attach($this->collection->id, ['position' => 2]);
    $this->va = $this->a->variants()->create(['sku' => 'LB-01-M', 'size' => 'M']);
    $this->vb = $this->b->variants()->create(['sku' => 'LB-02-M', 'size' => 'M']);
});

it('la tienda tiene recuento de resultados, «Ordenar por» y datos para ordenar', function () {
    $this->get(Urls::home($this->store))->assertOk()
        ->assertSee('Mostrando los 2 resultados')
        ->assertSee('Ordenar por precio: bajo a alto')
        ->assertSee('data-price="3000"', false)
        ->assertSee('data-pagination', false);
});

it('la ficha tiene cantidad, SKU, categorías, pestañas y productos relacionados', function () {
    $this->get(Urls::product($this->store, $this->a->slug))->assertOk()
        ->assertSee('name="quantity"', false)
        ->assertSee('SKU:')
        ->assertSee('Categoría')
        ->assertSeeInOrder(['Descripción', 'Información adicional', 'Envíos y devoluciones'])
        ->assertSee('Productos relacionados')
        ->assertSee('Otra pieza');
});

it('añadir con cantidad da el mensaje de WooCommerce', function () {
    $this->postJson(route('cart.add'), ['variant_id' => $this->va->id, 'quantity' => 2])
        ->assertOk()->assertJson(['message' => '2 × «La esencia» se han añadido a tu carrito.', 'count' => 2]);
});

it('el carrito es una tabla con «Actualizar carrito», quitar (×), calcular envío y «Finalizar compra»', function () {
    $this->postJson(route('cart.add'), ['variant_id' => $this->va->id]);
    $this->postJson(route('cart.add'), ['variant_id' => $this->vb->id]);

    $this->get(route('cart.show'))->assertOk()
        ->assertSee('Totales del carrito')->assertSee('Calcular envío')->assertSee('Actualizar carrito')->assertSee('Finalizar compra')
        ->assertSee('name="quantities['.$this->va->id.']"', false);

    $this->post(route('cart.update'), ['quantities' => [$this->va->id => 3, $this->vb->id => 1], 'update' => 1])
        ->assertRedirect(route('cart.show'))->assertSessionHas('cart.updated', 'Carrito actualizado.');
    $this->get(route('cart.show'))->assertSee('90,00 €'); // 3 × 30 €

    $this->post(route('cart.update'), ['quantities' => [$this->va->id => 3, $this->vb->id => 1], 'remove' => $this->vb->id])
        ->assertSessionHas('cart.updated', 'Producto eliminado del carrito.');
    $this->get(route('cart.show'))->assertDontSee('Otra pieza');
});

it('«Agotado» se ve en la tarjeta y en la ficha, y no deja añadir al carrito', function () {
    app(ConfigureStock::class)($this->a->id, StockStatus::OutOfStock, false);

    $this->get(Urls::home($this->store))->assertOk()->assertSee('class="sf-soldout"', false);
    $this->get(Urls::product($this->store, $this->a->slug))->assertOk()->assertSee('Agotado.');
    $this->postJson(route('cart.add'), ['variant_id' => $this->va->id, 'quantity' => 1])->assertJson(['ok' => false]);
});

it('la cantidad del inventario se reparte entre las tallas del carrito', function () {
    $vl = $this->a->variants()->create(['sku' => 'LB-01-L', 'size' => 'L']);
    app(ConfigureStock::class)($this->a->id, StockStatus::InStock, true, 3, loadedQuantity: 0);

    $this->postJson(route('cart.add'), ['variant_id' => $this->va->id, 'quantity' => 2])->assertJson(['ok' => true]);
    $this->postJson(route('cart.add'), ['variant_id' => $vl->id, 'quantity' => 2]);

    $this->get(route('cart.show'))->assertOk()->assertSee('Solo queda 1 unidad.');
});
