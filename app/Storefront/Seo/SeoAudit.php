<?php

namespace App\Storefront\Seo;

use App\Modules\Catalog\Contracts\ProductStatus;
use App\Modules\Catalog\Infrastructure\Models\BaseGarment;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Catalog\Infrastructure\Models\ProductImage;
use App\Modules\Stores\Infrastructure\Models\Store;

/**
 * Semáforo SEO de un artículo: comprueba las reglas de docs/seo/README.md y da una
 * nota de 0 a 100 con lo que falta. No llama a ninguna IA: son reglas fijas.
 *
 * @phpstan-type Check array{level: 'ok'|'warning'|'error', message: string, points: int}
 */
class SeoAudit
{
    /**
     * @return array{score: int, checks: list<Check>, title: string, description: string}
     */
    public function product(Product $product): array
    {
        $store = Store::query()->find($product->store_id);
        $garment = $product->base_garment_id === null ? null : BaseGarment::query()->whereKey($product->base_garment_id)->value('name');
        $title = $store === null ? (string) $product->seo_title : SeoBuilder::productTitle($product, $store, $garment);
        $description = $store === null ? (string) $product->seo_description : SeoBuilder::productDescription($product, $store, $garment);
        $images = $product->images()->where('is_swatch', false)->get();
        $words = SeoText::words(SeoText::plain($product->description));

        $checks = [];
        $add = function (bool $ok, string $level, string $okText, string $badText, int $points) use (&$checks): void {
            $checks[] = $ok ? ['level' => 'ok', 'message' => $okText, 'points' => 0] : ['level' => $level, 'message' => $badText, 'points' => $points];
        };

        $len = mb_strlen($title);
        $add($len >= SeoText::TITLE_MIN && $len <= SeoText::TITLE_MAX, 'warning',
            "Título para Google: {$len} caracteres.",
            "El título para Google tiene {$len} caracteres: lo ideal es entre ".SeoText::TITLE_MIN.' y '.SeoText::TITLE_MAX.'.', 10);
        $add(filled($product->seo_title), 'warning', 'Título SEO escrito a mano.', 'Sin título SEO propio: se usa uno automático. Mejor escribir uno con la palabra clave (p. ej. «Camiseta Farruquito …»).', 5);

        $dlen = mb_strlen($description);
        $add($dlen >= SeoText::DESCRIPTION_MIN && $dlen <= SeoText::DESCRIPTION_MAX, 'warning',
            "Descripción para Google: {$dlen} caracteres.",
            "La descripción para Google tiene {$dlen} caracteres: lo ideal es entre ".SeoText::DESCRIPTION_MIN.' y '.SeoText::DESCRIPTION_MAX.'.', 10);
        $add(filled($product->seo_description), 'warning', 'Descripción SEO escrita a mano.', 'Sin descripción SEO propia: se resume la del artículo. Mejor una que invite a pulsar.', 5);

        $add($words > 0, 'error', '', 'El artículo no tiene descripción: Google no tiene texto que entender.', 20);
        if ($words > 0) {
            $add($words >= 40, 'warning', "Descripción del artículo: {$words} palabras.", "La descripción del artículo tiene {$words} palabras: con 40 o más (tejido, corte, historia del diseño) posiciona mejor.", 10);
        }

        $add($images->isNotEmpty(), 'error', '', 'Sin fotos: Google Imágenes es una gran fuente de visitas en moda.', 20);
        if ($images->isNotEmpty()) {
            $add($images->count() >= 3, 'warning', "{$images->count()} fotos.", "Solo {$images->count()} foto(s): con 3 o más (frontal, espalda, detalle o modelo) mejora el clic.", 5);
            $missingAlt = $images->filter(fn (ProductImage $i): bool => blank($i->getAttribute('alt')) || $i->getAttribute('alt') === $product->name)->count();
            $add($missingAlt === 0, 'warning', 'Todas las fotos tienen texto alternativo propio.', "{$missingAlt} foto(s) sin texto alternativo descriptivo (qué se ve: prenda, color, diseño).", 10);
        }

        $duplicated = Product::query()->where('store_id', $product->store_id)->whereKeyNot($product->getKey())
            ->whereNotNull('seo_title')->where('seo_title', $product->seo_title)->exists();
        $add(! $duplicated, 'warning', 'Título único en la tienda.', 'Otro artículo de la tienda tiene el mismo título SEO: Google los confunde.', 10);

        $add($product->status === ProductStatus::Published, 'warning', 'Publicado: Google puede verlo.', 'En borrador: Google no lo ve hasta que se publique.', 5);

        $score = max(0, 100 - array_sum(array_column($checks, 'points')));

        return ['score' => $score, 'checks' => $checks, 'title' => $title, 'description' => $description];
    }

    public static function color(int $score): string
    {
        return match (true) {
            $score >= 90 => 'success',
            $score >= 60 => 'warning',
            default => 'danger',
        };
    }
}
