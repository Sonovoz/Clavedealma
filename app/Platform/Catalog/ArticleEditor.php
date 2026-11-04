<?php

namespace App\Platform\Catalog;

use App\Modules\Catalog\Application\GenerateVariants;
use App\Modules\Catalog\Application\PublicationCheck;
use App\Modules\Catalog\Contracts\ProductStatus;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Inventory\Application\SetStockMode;
use App\Modules\Inventory\Contracts\StockMode;
use App\Modules\Stores\Infrastructure\Models\Store;
use Illuminate\Support\Facades\DB;

/**
 * Lo que hace la ficha de artículo del panel al guardar, coordinando Catálogo,
 * Tiendas e Inventario: genera las variantes pedidas, fija su modo de venta y
 * comprueba que el artículo se puede publicar (si no, lo deja en borrador).
 *
 * @phpstan-type Generator array{colors?: list<array{name?: ?string, hex?: ?string}>, sizes?: array<array-key, string>, one_size?: bool, stock_mode?: ?string, units?: int|string|null, threshold?: int|string|null}
 */
class ArticleEditor
{
    public function __construct(
        private readonly GenerateVariants $generateVariants,
        private readonly SetStockMode $setStockMode,
        private readonly PublicationCheck $publicationCheck,
    ) {}

    /**
     * ¿Ha pedido el usuario crear variantes?
     *
     * @param  Generator  $generator
     */
    public static function wantsVariants(array $generator): bool
    {
        return self::colors($generator) !== [] || ($generator['sizes'] ?? []) !== [] || (bool) ($generator['one_size'] ?? false);
    }

    /**
     * @param  Generator  $generator
     * @return array{created: int, problems: list<string>}
     */
    public function afterSave(Product $product, array $generator, ?int $userId): array
    {
        $created = [];
        if (self::wantsVariants($generator)) {
            $subdomain = (string) Store::query()->whereKey($product->store_id)->value('subdomain');
            $sizes = ($generator['one_size'] ?? false) ? [] : array_values($generator['sizes'] ?? []);
            $created = ($this->generateVariants)($product, $subdomain, self::colors($generator), $sizes);

            $mode = StockMode::tryFrom((string) ($generator['stock_mode'] ?? '')) ?? StockMode::OnDemand;
            if ($mode !== StockMode::OnDemand && $created !== []) {
                $units = blank($generator['units'] ?? null) ? 0 : (int) $generator['units'];
                $threshold = blank($generator['threshold'] ?? null) ? null : (int) $generator['threshold'];
                // La ficha de stock nace al confirmar la transacción (evento VariantCreated).
                DB::afterCommit(function () use ($created, $mode, $units, $threshold, $userId): void {
                    foreach ($created as $variantId) {
                        ($this->setStockMode)($variantId, $mode, $units, $threshold, $userId);
                    }
                });
            }
        }

        $problems = [];
        if ($product->status === ProductStatus::Published) {
            $problems = $this->publicationCheck->problems($product);
            if ($problems !== []) {
                $product->forceFill(['status' => ProductStatus::Draft, 'published_at' => null])->save();
            }
        }

        return ['created' => count($created), 'problems' => $problems];
    }

    /**
     * @param  Generator  $generator
     * @return list<array{name: string, hex: ?string}>
     */
    public static function colors(array $generator): array
    {
        $colors = [];
        foreach ($generator['colors'] ?? [] as $color) {
            if (filled($color['name'] ?? null)) {
                $colors[] = ['name' => trim((string) $color['name']), 'hex' => $color['hex'] ?? null];
            }
        }

        return $colors;
    }
}
