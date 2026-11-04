<?php

namespace App\Storefront\Seo;

use App\Modules\Catalog\Infrastructure\Models\BaseGarment;
use App\Modules\Catalog\Infrastructure\Models\Collection;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Catalog\Infrastructure\Models\ProductImage;
use App\Modules\Catalog\Infrastructure\Models\ProductVariant;
use App\Modules\Stores\Infrastructure\Models\Store;
use App\Platform\Ai\AiException;
use App\Platform\Ai\TextGenerator;
use App\Support\Money;

/**
 * Asistente SEO con IA para el panel. Solo PROPONE textos (una persona los revisa y
 * guarda) y solo con datos del catálogo, nunca de clientes. Las reglas que sigue son
 * las de docs/seo/README.md; las longitudes se vuelven a comprobar aquí.
 */
class SeoAssistant
{
    /** Reglas comunes que se le dan a la IA en cada petición (orden estable). */
    private const RULES = <<<'TXT'
Eres el redactor SEO de Clave de Alma, la plataforma de merchandising oficial de artistas
(camisetas, sudaderas, gorras y bolsas fabricadas bajo pedido en España). Escribes en
español de España para la ficha de un producto o una página de tienda.

Reglas obligatorias:
1. Usa SOLO los datos que te doy. No inventes nada: ni biografía del artista, ni premios,
   ni materiales, medidas, cantidades, «edición limitada», envío gratis, descuentos o plazos
   que no aparezcan en los datos. Si falta un dato, no lo menciones.
2. Incluye de forma natural el nombre del artista y el tipo de prenda (la búsqueda típica
   es «camiseta <artista>», «sudadera <artista>», «merchandising oficial <artista>»).
3. Nada de relleno de palabras clave, mayúsculas gritadas, emojis, signos de exclamación
   repetidos ni promesas exageradas («el mejor», «único en el mundo»).
4. Tono: cuidado, cercano y fiel a la voz de la colección. Frases claras.
5. Responde SOLO con un objeto JSON con exactamente las claves que se piden.
TXT;

    public function __construct(private readonly TextGenerator $ai) {}

    public function available(): bool
    {
        return $this->ai->available();
    }

    /**
     * Título (≤ 60) y descripción (120-155) para Google de un artículo.
     *
     * @return array{seo_title: string, seo_description: string}
     */
    public function productSeo(Product $product): array
    {
        $data = $this->ai->json(self::RULES, implode("\n", [
            'Tarea: escribe el título y la descripción que verá Google para este producto.',
            '- seo_title: entre 40 y 60 caracteres, empieza por lo que es el producto (prenda + nombre del diseño) y termina con el artista. No añadas «Clave de Alma».',
            '- seo_description: entre 120 y 155 caracteres, invita a pulsar; menciona la prenda, el diseño y que es oficial del artista.',
            'Claves JSON: seo_title, seo_description.',
            '',
            'Datos del producto:',
            $this->productData($product),
        ]), 600);

        return [
            'seo_title' => $this->text($data, 'seo_title', SeoText::TITLE_MAX),
            'seo_description' => $this->text($data, 'seo_description', SeoText::DESCRIPTION_MAX),
        ];
    }

    /**
     * Descripción mejorada de la ficha (párrafos de texto plano), con lo que ya hay.
     */
    public function productDescription(Product $product): string
    {
        $data = $this->ai->json(self::RULES, implode("\n", [
            'Tarea: mejora la descripción de la ficha de este producto para que la entienda Google y emocione al fan.',
            '- Entre 80 y 160 palabras, en 2 o 3 párrafos cortos separados por una línea en blanco.',
            '- Primer párrafo: qué es (prenda + diseño + artista). Después, la historia del diseño si está en los datos, y por último tejido, composición y corte si están en los datos.',
            '- Conserva el sentido y las ideas de la descripción actual; no inventes.',
            'Clave JSON: description.',
            '',
            'Datos del producto:',
            $this->productData($product),
        ]), 1200);

        $text = trim((string) ($data['description'] ?? ''));
        if ($text === '') {
            throw new AiException('La IA no ha devuelto una descripción. Inténtalo de nuevo.');
        }

        // A HTML de la ficha (siempre escapado).
        return implode('', array_map(fn (string $p): string => '<p>'.e(trim($p)).'</p>', preg_split('/\R\s*\R/u', $text) ?: [$text]));
    }

    /**
     * Textos alternativos para las fotos, a partir de los datos del artículo (la IA no ve las
     * fotos: hay que revisar que cada texto coincide con lo que se ve).
     *
     * @param  list<array{color: ?string, swatch: bool}>  $photos  en el orden de la ficha
     * @return list<string>
     */
    public function altTexts(Product $product, array $photos): array
    {
        if ($photos === []) {
            return [];
        }
        $list = [];
        foreach ($photos as $k => $photo) {
            $list[] = ($k + 1).'. '.($photo['swatch'] ? 'muestra de tejido' : 'foto').($photo['color'] ? ' del color '.$photo['color'] : ' general').($k === 0 ? ' (portada)' : '');
        }

        $data = $this->ai->json(self::RULES, implode("\n", [
            'Tarea: escribe el texto alternativo (atributo alt) de cada foto de este producto.',
            '- Uno por foto, en el mismo orden. Máximo 120 caracteres cada uno.',
            '- Describe lo que se verá: prenda, color, diseño y artista. No empieces por «Imagen de» ni «Foto de».',
            '- Como no ves la foto, usa solo los datos; varía el texto entre fotos (portada, espalda, detalle) sin inventar.',
            'Clave JSON: alts (lista de textos).',
            '',
            'Fotos:',
            implode("\n", $list),
            '',
            'Datos del producto:',
            $this->productData($product),
        ]), 800);

        $alts = array_values(array_filter(array_map(fn ($a): string => SeoText::limit(trim((string) $a), 125), (array) ($data['alts'] ?? []))));
        if (count($alts) < count($photos)) {
            throw new AiException('La IA no ha devuelto un texto para cada foto. Inténtalo de nuevo.');
        }

        return array_slice($alts, 0, count($photos));
    }

    /**
     * @return array{seo_title: string, seo_description: string}
     */
    public function storeSeo(Store $store): array
    {
        $collections = Collection::query()->where('store_id', $store->id)->orderBy('position')->pluck('name')->implode(', ');
        $garments = BaseGarment::query()->where('store_id', $store->id)->pluck('name')->implode(', ');

        $data = $this->ai->json(self::RULES, implode("\n", [
            'Tarea: escribe el título y la descripción que verá Google para la PORTADA de la tienda oficial del artista.',
            '- seo_title: entre 40 y 60 caracteres, con el nombre del artista y «tienda oficial» o «merchandising oficial».',
            '- seo_description: entre 120 y 155 caracteres: qué se puede comprar (tipos de prenda, colecciones) y que es oficial.',
            'Claves JSON: seo_title, seo_description.',
            '',
            'Datos de la tienda:',
            '- Artista / tienda: '.$store->name,
            '- Frase de portada: '.($store->tagline ?? '—'),
            '- Presentación: '.SeoText::plain($store->bio),
            '- Colecciones: '.($collections ?: '—'),
            '- Prendas: '.($garments ?: '—'),
        ]), 600);

        return [
            'seo_title' => $this->text($data, 'seo_title', SeoText::TITLE_MAX),
            'seo_description' => $this->text($data, 'seo_description', SeoText::DESCRIPTION_MAX),
        ];
    }

    private function productData(Product $product): string
    {
        $store = Store::query()->find($product->store_id);
        $garment = $product->base_garment_id === null ? null : BaseGarment::query()->find($product->base_garment_id);
        $variants = $product->variants()->where('active', true)->get();
        $colors = $variants->pluck('color')->filter()->unique()->implode(', ');
        $sizes = $variants->pluck('size')->filter()->unique()->implode(', ');
        $prices = $variants->map(fn (ProductVariant $v): int => $v->price_cents ?? $product->price_cents);

        return implode("\n", array_filter([
            '- Artista / tienda: '.($store->name ?? '—'),
            '- Nombre del diseño: '.$product->name,
            '- Referencia: '.($product->reference ?? '—'),
            '- Colecciones: '.($product->collections()->pluck('name')->implode(', ') ?: '—'),
            '- Prenda: '.($garment->name ?? '—'),
            $garment?->composition ? '- Composición: '.$garment->composition : null,
            $garment?->getAttribute('material') ? '- Tejido: '.$garment->getAttribute('material') : null,
            $garment?->fit ? '- Corte: '.$garment->fit : null,
            '- Colores: '.($colors ?: 'uno'),
            '- Tallas: '.($sizes ?: 'talla única'),
            '- Precio con IVA: '.Money::fromCents((int) ($prices->min() ?? $product->price_cents))->format(),
            '- Plazo: '.$product->leadTime()->label(),
            '- Descripción actual: '.(SeoText::plain($product->description) ?: '—'),
        ]));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function text(array $data, string $key, int $max): string
    {
        $text = trim(str_replace(['«', '»', '"'], '', (string) ($data[$key] ?? '')));
        if ($text === '') {
            throw new AiException('La IA no ha devuelto «'.$key.'». Inténtalo de nuevo.');
        }

        return SeoText::limit($text, $max);
    }

    /**
     * @return list<array{color: ?string, swatch: bool}>
     */
    public static function photosOf(Product $product): array
    {
        return $product->images()->get()->map(fn (ProductImage $i): array => ['color' => $i->color, 'swatch' => $i->is_swatch])->values()->all();
    }
}
