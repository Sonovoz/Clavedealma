<?php

namespace App\Storefront\Seo;

use App\Modules\Catalog\Contracts\ProductStatus;
use App\Modules\Catalog\Infrastructure\Models\Collection;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Stores\Infrastructure\Models\Store;
use App\Storefront\Designs;
use App\Storefront\StoreAccess;
use App\Storefront\Urls;

/**
 * Metaetiquetas y datos estructurados de cada página pública, según las reglas de
 * docs/seo/README.md. La canónica sigue STORE_URL_MODE: una sola dirección por página.
 */
class SeoBuilder
{
    public static function indexing(): bool
    {
        $forced = config('clavedealma.seo.indexing');

        return $forced === null || $forced === '' ? app()->isProduction() : filter_var($forced, FILTER_VALIDATE_BOOL);
    }

    /** Título que verá Google: el propio o «Nombre · Prenda Artista | Clave de Alma». */
    public static function productTitle(Product $product, Store $store, ?string $garment): string
    {
        return $product->seo_title ?: SeoText::title([$product->name, ($garment ?? 'Merchandising').' '.$store->name]);
    }

    /** Descripción que verá Google: la propia o un resumen de la descripción del artículo. */
    public static function productDescription(Product $product, Store $store, ?string $garment): string
    {
        if (filled($product->seo_description)) {
            return (string) $product->seo_description;
        }
        $text = trim(SeoText::plain($product->description).' '.($garment ? $garment.' oficial de '.$store->name.'.' : ''));

        return SeoText::limit($text !== '' ? $text : $product->name.' · merchandising oficial de '.$store->name.'. Fabricado bajo pedido.', SeoText::DESCRIPTION_MAX);
    }

    /** Páginas de compra (cesta, datos, pago, confirmación): nunca se indexan. */
    public function checkout(string $title): SeoMeta
    {
        return new SeoMeta(
            title: $title.' | Clave de Alma',
            description: 'Compra en Clave de Alma: un solo carrito y un solo pago para todas las tiendas.',
            canonical: url()->current(),
            index: false,
        );
    }

    /**
     * @param  list<array{name: string, url: string}>  $stores
     */
    public function market(array $stores): SeoMeta
    {
        $url = route('market');

        return new SeoMeta(
            title: 'Clave de Alma · Merchandising oficial de artistas',
            description: 'Tiendas oficiales de artistas: camisetas, sudaderas y piezas con alma, fabricadas bajo pedido en España. Un solo carrito y un solo pago para todas.',
            canonical: $url,
            index: self::indexing(),
            image: Urls::designAsset('matriz', 'logo.png'),
            jsonLd: [
                $this->organization(),
                ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => SeoText::SITE, 'url' => $url, 'inLanguage' => 'es-ES'],
                $this->itemList(array_map(fn (array $s): string => $s['url'], $stores)),
            ],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $cards
     */
    public function store(Store $store, array $cards): SeoMeta
    {
        $url = Urls::home($store);
        $title = $store->seo_title ?: SeoText::title([$store->name.' · Tienda oficial']);
        $description = $store->seo_description ?: SeoText::limit(
            trim(($store->tagline ? $store->tagline.'. ' : '').'Merchandising oficial de '.$store->name.': '.$this->garmentList($cards).'. Fabricado bajo pedido, envío a España y Portugal.'),
            SeoText::DESCRIPTION_MAX,
        );

        return new SeoMeta(
            title: $title,
            description: $description,
            canonical: $url,
            index: self::indexing() && ! StoreAccess::isPreview($store),
            image: $this->storeImage($store) ?? ($cards[0]['image'] ?? null),
            jsonLd: [
                $this->breadcrumbs([[SeoText::SITE, route('market')], [$store->name, $url]]),
                $this->itemList(array_map(fn (array $c): string => (string) $c['url'], $cards)),
            ],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $cards
     */
    public function collection(Store $store, Collection $collection, array $cards): SeoMeta
    {
        $url = Urls::collection($store, $collection->slug);

        return new SeoMeta(
            title: $collection->seo_title ?: SeoText::title([$collection->name, $store->name]),
            description: $collection->seo_description ?: SeoText::limit(
                trim(SeoText::plain($collection->description).' Colección oficial '.$collection->name.' de '.$store->name.': '.count($cards).' piezas fabricadas bajo pedido.'),
                SeoText::DESCRIPTION_MAX,
            ),
            canonical: $url,
            index: self::indexing() && ! StoreAccess::isPreview($store),
            image: $cards[0]['image'] ?? null,
            jsonLd: [
                $this->breadcrumbs([[SeoText::SITE, route('market')], [$store->name, Urls::home($store)], [$collection->name, $url]]),
                $this->itemList(array_map(fn (array $c): string => (string) $c['url'], $cards)),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $item  ficha de StoreCatalog::product()
     */
    public function product(Store $store, array $item): SeoMeta
    {
        /** @var Product $product */
        $product = $item['model'];
        $url = Urls::product($store, $product->slug);
        $garment = $item['garment']['name'] ?? null;
        $images = array_values(array_map(fn (array $i): string => (string) $i['url'], $item['images']));

        $description = self::productDescription($product, $store, $garment);

        $published = $product->status === ProductStatus::Published;
        $breadcrumb = [[SeoText::SITE, route('market')], [$store->name, Urls::home($store)]];
        if (($item['collections'][0] ?? null) !== null) {
            $breadcrumb[] = [(string) $item['collections'][0]['name'], (string) $item['collections'][0]['url']];
        }
        $breadcrumb[] = [$product->name, $url];

        $variants = $item['variants'];
        $prices = array_map(fn (array $v): int => (int) $v['price_cents'], $variants);
        $first = $variants[0] ?? null;

        return new SeoMeta(
            title: self::productTitle($product, $store, $garment),
            description: $description,
            canonical: $url,
            index: self::indexing() && $published && ! StoreAccess::isPreview($store),
            image: $images[0] ?? null,
            type: 'product',
            jsonLd: [
                $this->productLd($store, $item, $url, $images, $prices),
                $this->breadcrumbs($breadcrumb),
            ],
            extraOg: $first === null ? [] : [
                'product:price:amount' => SeoText::price(min($prices)),
                'product:price:currency' => 'EUR',
                'product:availability' => in_array(true, array_column($variants, 'available'), true) ? 'in stock' : 'out of stock',
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  list<string>  $images
     * @param  list<int>  $prices
     * @return array<string, mixed>
     */
    private function productLd(Store $store, array $item, string $url, array $images, array $prices): array
    {
        $variants = $item['variants'];
        $availability = function (array $v): string {
            return match (true) {
                ! $v['available'] => 'https://schema.org/OutOfStock',
                (bool) $v['made_to_order'] => 'https://schema.org/MadeToOrder',
                default => 'https://schema.org/InStock',
            };
        };
        $seller = ['@type' => 'Organization', 'name' => SeoText::SITE, 'url' => route('market')];
        $returns = [
            '@type' => 'MerchantReturnPolicy',
            'applicableCountry' => 'ES',
            'returnPolicyCategory' => $item['personalized'] ? 'https://schema.org/MerchantReturnNotPermitted' : 'https://schema.org/MerchantReturnFiniteReturnWindow',
        ] + ($item['personalized'] ? [] : ['merchantReturnDays' => 14, 'returnMethod' => 'https://schema.org/ReturnByMail']);

        $offers = array_map(fn (array $v): array => array_filter([
            '@type' => 'Offer',
            'sku' => $v['sku'],
            'name' => trim(implode(' · ', array_filter([$v['color'], $v['size']]))) ?: null,
            'price' => SeoText::price((int) $v['price_cents']),
            'priceCurrency' => 'EUR',
            'availability' => $availability($v),
            'itemCondition' => 'https://schema.org/NewCondition',
            'url' => $url,
            'seller' => $seller,
            'hasMerchantReturnPolicy' => $returns,
        ], fn ($x) => $x !== null), $variants);

        $garment = $item['garment'];

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $item['name'],
            'description' => SeoText::plain((string) $item['description']) ?: null,
            'sku' => $item['reference'],
            'image' => $images,
            'url' => $url,
            'brand' => ['@type' => 'Brand', 'name' => $store->name],
            'material' => $garment['composition'] ?? null,
            'color' => implode(', ', array_column($item['colors'], 'name')) ?: null,
            'offers' => count($offers) > 1
                ? ['@type' => 'AggregateOffer', 'priceCurrency' => 'EUR', 'lowPrice' => SeoText::price(min($prices)), 'highPrice' => SeoText::price(max($prices)), 'offerCount' => count($offers), 'offers' => $offers]
                : ($offers[0] ?? null),
        ], fn ($x) => $x !== null && $x !== [] && $x !== '');
    }

    /**
     * @return array<string, mixed>
     */
    private function organization(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => SeoText::SITE,
            'url' => route('market'),
            'logo' => Urls::designAsset('matriz', 'logo.png'),
            'description' => 'Plataforma de merchandising oficial de artistas: diseño, fabricación bajo pedido, venta y envío.',
        ];
    }

    /**
     * @param  list<array{0: string, 1: string}>  $items
     * @return array<string, mixed>
     */
    private function breadcrumbs(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn (array $it, int $k): array => ['@type' => 'ListItem', 'position' => $k + 1, 'name' => $it[0], 'item' => $it[1]], $items, array_keys($items)),
        ];
    }

    /**
     * @param  list<string>  $urls
     * @return array<string, mixed>
     */
    private function itemList(array $urls): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'itemListElement' => array_map(fn (string $u, int $k): array => ['@type' => 'ListItem', 'position' => $k + 1, 'url' => $u], $urls, array_keys($urls)),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $cards
     */
    private function garmentList(array $cards): string
    {
        $labels = ['camiseta' => 'camisetas', 'sudadera' => 'sudaderas', 'gorra' => 'gorras', 'tote' => 'tote bags'];
        $types = array_values(array_unique(array_filter(array_map(fn (array $c): ?string => $labels[$c['type'] ?? ''] ?? null, $cards))));

        return $types === [] ? 'piezas oficiales' : implode(', ', $types);
    }

    private function storeImage(Store $store): ?string
    {
        $design = Designs::resolve($store->design);

        return is_file(resource_path("designs/{$design}/tarjeta.webp")) ? Urls::designAsset($design, 'tarjeta.webp') : null;
    }
}
