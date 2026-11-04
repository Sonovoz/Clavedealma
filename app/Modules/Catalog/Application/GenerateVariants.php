<?php

namespace App\Modules\Catalog\Application;

use App\Modules\Catalog\Domain\SizeScale;
use App\Modules\Catalog\Domain\Sku;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Catalog\Infrastructure\Models\ProductVariant;
use Illuminate\Support\Str;

/**
 * Crea de una vez todas las combinaciones de colores × tallas de un artículo.
 * Las combinaciones que ya existen no se tocan (si estaban desactivadas, se reactivan).
 * Después ordena todas las variantes: por color y, dentro, por la escala de tallas.
 */
class GenerateVariants
{
    /**
     * @param  list<array{name: ?string, hex?: ?string}>  $colors  vacío = sin color
     * @param  list<?string>  $sizes  vacío = talla única
     * @return list<int> ids de las variantes nuevas
     */
    public function __invoke(Product $product, string $storeSubdomain, array $colors, array $sizes): array
    {
        $colors = $colors === [] ? [['name' => null, 'hex' => null]] : $colors;
        $sizes = $sizes === [] ? [null] : SizeScale::sort(array_map(SizeScale::normalize(...), $sizes));

        $existing = $product->variants()->get();
        $created = [];

        foreach ($colors as $color) {
            $name = blank($color['name'] ?? null) ? null : Str::ucfirst(trim((string) $color['name']));
            $hex = blank($color['hex'] ?? null) ? null : Str::lower((string) $color['hex']);

            foreach (array_unique($sizes) as $size) {
                $match = $existing->first(fn (ProductVariant $v): bool => Str::lower((string) $v->size) === Str::lower((string) $size)
                    && Str::lower((string) $v->color) === Str::lower((string) $name));

                if ($match !== null) {
                    $match->fill(['active' => true] + ($hex !== null ? ['color_hex' => $hex] : []))->save();

                    continue;
                }

                $variant = $product->variants()->create([
                    'sku' => $this->uniqueSku(Sku::suggest($storeSubdomain, $product->reference ?? $product->slug, $size, $name)),
                    'size' => $size,
                    'color' => $name,
                    'color_hex' => $hex,
                ]);
                $existing->push($variant);
                $created[] = $variant->id;
            }
        }

        $this->reorder($product);

        return $created;
    }

    /** Orden de las variantes: colores en orden de alta y tallas según la escala. */
    public function reorder(Product $product): void
    {
        $variants = $product->variants()->reorder()->orderBy('id')->get();
        $colors = $variants->pluck('color')->map(fn (?string $c): string => Str::lower((string) $c))->unique()->values()->all();

        $variants
            ->sortBy(fn (ProductVariant $v): array => [array_search(Str::lower((string) $v->color), $colors, true), SizeScale::rank($v->size), (string) $v->size])
            ->values()
            ->each(function (ProductVariant $v, int $position): void {
                if ($v->position !== $position) {
                    $v->forceFill(['position' => $position])->saveQuietly();
                }
            });
    }

    private function uniqueSku(string $sku): string
    {
        $candidate = $sku;
        for ($i = 2; ProductVariant::query()->where('sku', $candidate)->exists(); $i++) {
            $candidate = Str::limit($sku, 60, '').'-'.$i;
        }

        return $candidate;
    }
}
