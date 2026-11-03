<?php

namespace Database\Seeders;

use App\Modules\Catalog\Contracts\ProductStatus;
use App\Modules\Catalog\Domain\Sku;
use App\Modules\Catalog\Infrastructure\Models\BaseGarment;
use App\Modules\Catalog\Infrastructure\Models\Collection;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Inventory\Application\AdjustStock;
use App\Modules\Inventory\Contracts\StockMode;
use App\Modules\Inventory\Contracts\StockMovementReason;
use App\Modules\Inventory\Infrastructure\Models\StockLevel;
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
    public function run(AdjustStock $adjustStock): void
    {
        if (app()->isProduction()) {
            $this->command?->error('DemoSeeder no se ejecuta en producción.');

            return;
        }

        $garments = [
            'tee' => BaseGarment::query()->firstOrCreate(['code' => 'CAM-180'], ['name' => 'Camiseta unisex 180 g', 'material' => 'Algodón orgánico', 'weight_grams' => 180]),
            'hoodie' => BaseGarment::query()->firstOrCreate(['code' => 'SUD-320'], ['name' => 'Sudadera con capucha 320 g', 'material' => 'Algodón y poliéster reciclado', 'weight_grams' => 620]),
            'cap' => BaseGarment::query()->firstOrCreate(['code' => 'GOR-01'], ['name' => 'Gorra de béisbol', 'material' => 'Algodón', 'weight_grams' => 110]),
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

        // Una variante con stock limitado para ver el modo en acción.
        $level = StockLevel::query()->where('product_variant_id', Product::query()->where('slug', 'sudadera-logo')->firstOrFail()->variants()->firstOrFail()->id)->firstOrFail();
        if ($level->mode === StockMode::OnDemand) {
            $level->update(['mode' => StockMode::LimitedThenOnDemand, 'low_stock_threshold' => 2]);
            $adjustStock($level->product_variant_id, 5, StockMovementReason::Initial, 'Datos de demostración');
        }
    }
}
