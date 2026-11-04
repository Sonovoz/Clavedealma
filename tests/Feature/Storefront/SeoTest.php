<?php

use App\Modules\Catalog\Contracts\ProductStatus;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Stores\Contracts\StoreStatus;
use App\Modules\Stores\Infrastructure\Models\Store;
use App\Storefront\Urls;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->withoutVite();
    config()->set('clavedealma.seo.indexing', true);
    $this->store = Store::factory()->create(['subdomain' => 'luna', 'name' => 'Luna Bruma', 'status' => StoreStatus::Published, 'tagline' => 'Canciones para noches largas']);
    $this->product = Product::factory()->create([
        'store_id' => $this->store->id, 'name' => 'Camiseta Noches', 'reference' => 'LB-01',
        'status' => ProductStatus::Published, 'price_cents' => 2990, 'description' => '<p>Algodón suave con la portada del disco.</p>',
    ]);
    $this->product->variants()->create(['sku' => 'LB-01-M', 'size' => 'M', 'color' => 'Negro']);
    $this->product->variants()->create(['sku' => 'LB-01-3XL', 'size' => '3XL', 'color' => 'Negro', 'price_cents' => 3290]);
    $this->product->images()->create(['path' => 'products/luna/lb-01.webp', 'alt' => 'Camiseta Noches negra']);
});

/** @return list<array<string, mixed>> */
function jsonLd(string $html): array
{
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);

    return array_map(fn (string $j): array => json_decode($j, true, flags: JSON_THROW_ON_ERROR), $m[1]);
}

it('la ficha tiene título, descripción, canónica, Open Graph y datos de producto válidos', function () {
    $url = Urls::product($this->store, $this->product->slug);
    $html = $this->get($url)->assertOk()
        ->assertSee('<title>Camiseta Noches · Merchandising Luna Bruma | Clave de Alma</title>', false)
        ->assertSee('<link rel="canonical" href="'.$url.'">', false)
        ->assertSee('<meta name="robots" content="index, follow, max-image-preview:large">', false)
        ->assertSee('<meta property="product:price:amount" content="29.90">', false)
        ->getContent();

    $blocks = collect(jsonLd($html))->keyBy('@type');
    $product = $blocks['Product'];
    expect($product['name'])->toBe('Camiseta Noches')
        ->and($product['brand']['name'])->toBe('Luna Bruma')
        ->and($product['offers']['@type'])->toBe('AggregateOffer')
        ->and($product['offers']['lowPrice'])->toBe('29.90')
        ->and($product['offers']['highPrice'])->toBe('32.90')
        ->and($product['offers']['offers'][0]['availability'])->toBe('https://schema.org/MadeToOrder')
        ->and($product['offers']['offers'][0]['hasMerchantReturnPolicy']['merchantReturnDays'])->toBe(14)
        ->and($blocks['BreadcrumbList']['itemListElement'])->toHaveCount(3);
});

it('el título SEO propio manda y nunca pasa de 60 caracteres', function () {
    $this->product->update(['seo_title' => 'Camiseta negra Luna Bruma · Noches largas', 'seo_description' => 'Descripción a medida para Google.']);
    $this->get(Urls::product($this->store, $this->product->slug))
        ->assertSee('<title>Camiseta negra Luna Bruma · Noches largas</title>', false)
        ->assertSee('content="Descripción a medida para Google."', false);

    $this->product->update(['seo_title' => null, 'name' => str_repeat('Nombre larguísimo ', 6)]);
    $html = $this->get(Urls::product($this->store, $this->product->fresh()->slug))->getContent();
    preg_match('#<title>(.*?)</title>#', $html, $m);
    expect(mb_strlen(html_entity_decode($m[1])))->toBeLessThanOrEqual(60);
});

it('no indexa tiendas ocultas, borradores ni el servidor de pruebas', function () {
    $this->store->update(['status' => StoreStatus::Hidden]);
    $this->actingAs(User::factory()->withRole(Role::CatalogManager)->create())
        ->get(Urls::home($this->store))->assertSee('content="noindex, nofollow"', false);

    config()->set('clavedealma.seo.indexing', false);
    $this->store->update(['status' => StoreStatus::Published]);
    $this->get(Urls::home($this->store))->assertSee('content="noindex, nofollow"', false);
    $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /')->assertDontSee('Sitemap:');
});

it('el mapa del sitio lista solo lo publicado, con sus fotos, y robots lo anuncia', function () {
    Product::factory()->create(['store_id' => $this->store->id, 'name' => 'Borrador secreto', 'status' => ProductStatus::Draft]);
    Store::factory()->create(['subdomain' => 'oculta', 'status' => StoreStatus::Hidden]);

    $xml = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();
    expect($xml)->toContain(Urls::product($this->store, $this->product->slug))
        ->toContain(Urls::home($this->store))
        ->toContain('products/luna/lb-01.webp')
        ->not->toContain('borrador-secreto')
        ->not->toContain('/tienda/oculta');
    expect(simplexml_load_string($xml))->not->toBeFalse();

    $this->get('/robots.txt')->assertSee('Disallow: /admin')->assertSee('Sitemap: '.route('seo.sitemap'));
});

it('la matriz se presenta como organización y sitio web', function () {
    $types = collect(jsonLd($this->get('/')->assertOk()->getContent()))->pluck('@type')->all();
    expect($types)->toBe(['Organization', 'WebSite', 'ItemList']);
});
