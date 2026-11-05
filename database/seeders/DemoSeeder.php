<?php

namespace Database\Seeders;

use App\Modules\Catalog\Contracts\ProductStatus;
use App\Modules\Catalog\Domain\Sku;
use App\Modules\Catalog\Infrastructure\Models\BaseGarment;
use App\Modules\Catalog\Infrastructure\Models\Collection;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Inventory\Application\ConfigureStock;
use App\Modules\Inventory\Contracts\StockStatus;
use App\Modules\Inventory\Infrastructure\Models\ProductStock;
use App\Modules\Stores\Contracts\StoreStatus;
use App\Modules\Stores\Contracts\TaxRegime;
use App\Modules\Stores\Infrastructure\Models\Artist;
use App\Modules\Stores\Infrastructure\Models\Store;
use Illuminate\Database\Seeder;

/**
 * Datos ficticios para ver el panel con contenido. NO usar en producción.
 *
 *   php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(ConfigureStock $configureStock): void
    {
        if (app()->isProduction()) {
            $this->command?->error('DemoSeeder no se ejecuta en producción.');

            return;
        }

        // Cada tienda tiene sus prendas base (se crean dentro del bucle).
        $garmentData = [
            'tee' => ['CAM-180', 'Camiseta unisex 180 g', 'Algodón orgánico', '100 % algodón orgánico', 180],
            'hoodie' => ['SUD-320', 'Sudadera con capucha 320 g', 'Algodón y poliéster reciclado', '80 % algodón, 20 % poliéster reciclado', 620],
            'cap' => ['GOR-01', 'Gorra de béisbol', 'Algodón', '100 % algodón', 110],
        ];

        $stores = [
            ['artist' => 'Luna Bruma', 'store' => 'Luna Bruma', 'subdomain' => 'lunabruma', 'commission' => 2500, 'tagline' => 'Canciones para noches largas.'],
            ['artist' => 'Los Arcos', 'store' => 'Los Arcos Merch', 'subdomain' => 'losarcos', 'commission' => 3000, 'tagline' => 'Rock de barrio desde 2009.'],
        ];

        foreach ($stores as $data) {
            $artist = Artist::query()->firstOrCreate(['display_name' => $data['artist']], ['tax_regime' => TaxRegime::SelfEmployed, 'country' => 'ES']);
            $store = Store::query()->firstOrCreate(['subdomain' => $data['subdomain']], [
                'artist_id' => $artist->id,
                'name' => $data['store'],
                'status' => StoreStatus::Published,
                'commission_bps' => $data['commission'],
                'tagline' => $data['tagline'],
            ]);

            $garments = [];
            foreach ($garmentData as $key => [$code, $name, $material, $composition, $grams]) {
                $garments[$key] = BaseGarment::query()->firstOrCreate(
                    ['store_id' => $store->id, 'code' => $code],
                    ['name' => $name, 'material' => $material, 'composition' => $composition, 'weight_grams' => $grams],
                );
            }

            $collection = Collection::query()->firstOrCreate(['store_id' => $store->id, 'slug' => 'gira-2026'], ['name' => 'Gira 2026']);

            $products = [
                ['name' => 'Camiseta Gira 2026', 'garment' => 'tee', 'price' => 2490, 'sizes' => ['S', 'M', 'L', 'XL'], 'colors' => ['Negro', 'Blanco']],
                ['name' => 'Sudadera Logo', 'garment' => 'hoodie', 'price' => 4990, 'sizes' => ['S', 'M', 'L', 'XL'], 'colors' => ['Negro']],
                ['name' => 'Gorra Bordada', 'garment' => 'cap', 'price' => 2290, 'sizes' => [null], 'colors' => ['Negro', 'Arena']],
            ];

            foreach ($products as $p) {
                $product = Product::query()->firstOrCreate(['store_id' => $store->id, 'slug' => str($p['name'])->slug()->toString()], [
                    'name' => $p['name'],
                    'base_garment_id' => $garments[$p['garment']]->id,
                    'description' => "Diseño oficial de {$data['artist']}. Fabricado bajo pedido.",
                    'price_cents' => $p['price'],
                    'status' => ProductStatus::Published,
                ]);
                $product->collections()->syncWithoutDetaching([$collection->id]);

                $position = 0;
                foreach ($p['colors'] as $color) {
                    foreach ($p['sizes'] as $size) {
                        $product->variants()->firstOrCreate(
                            ['sku' => Sku::suggest($store->subdomain, $product->slug, $size, $color)],
                            ['size' => $size, 'color' => $color, 'position' => $position++],
                        );
                    }
                }
            }
        }

        // Un producto con cantidad limitada para ver el inventario en acción (ADR 0022).
        $sudadera = Product::query()->where('slug', 'sudadera-logo')->firstOrFail();
        if (! ProductStock::query()->where('product_id', $sudadera->id)->exists()) {
            $configureStock($sudadera->id, StockStatus::InStock, true, 5, 2);
        }
    }
}
