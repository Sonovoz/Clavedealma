<?php

use App\Modules\Catalog\Application\PublicationCheck;
use App\Modules\Catalog\Infrastructure\Models\BaseGarment;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Catalog\Infrastructure\Models\VariantPrice;
use App\Modules\Stores\Infrastructure\Models\Store;

it('guarda el historial de precios y calcula la rebaja legal', function () {
    $product = Product::factory()->create(['store_id' => Store::factory()->create()->id, 'price_cents' => 4995]);
    $variant = $product->variants()->create(['sku' => 'H-1', 'size' => 'M']);
    $grande = $product->variants()->create(['sku' => 'H-2', 'size' => '3XL', 'price_cents' => 5495]);

    $this->travel(40)->days();
    $product->update(['price_cents' => 4499]);

    expect($variant->prices()->pluck('price_cents')->all())->toBe([4995, 4499])
        ->and($variant->discount())->toBe(['cents' => 4995, 'percent' => 10])
        ->and($grande->prices()->count())->toBe(1) // tiene precio propio: no cambia
        ->and($grande->discount())->toBeNull();

    expect(fn () => VariantPrice::first()->update(['price_cents' => 1]))->toThrow(LogicException::class);
});

it('no deja publicar un artículo incompleto', function () {
    $garment = BaseGarment::factory()->create(['composition' => null]);
    $product = Product::factory()->create(['store_id' => Store::factory()->create()->id, 'base_garment_id' => $garment->id]);

    expect(app(PublicationCheck::class)->problems($product))->toHaveCount(3);

    $garment->update(['composition' => '100 % algodón']);
    $product->variants()->create(['sku' => 'P-1']);
    $product->images()->create(['path' => 'products/x.jpg']);

    expect(app(PublicationCheck::class)->problems($product))->toBe([]);
});
