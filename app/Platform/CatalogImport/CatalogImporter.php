<?php

namespace App\Platform\CatalogImport;

use App\Modules\Catalog\Application\PublicationCheck;
use App\Modules\Catalog\Contracts\ProductStatus;
use App\Modules\Catalog\Domain\Sku;
use App\Modules\Catalog\Infrastructure\Models\BaseGarment;
use App\Modules\Catalog\Infrastructure\Models\Collection;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Catalog\Infrastructure\Models\ProductVariant;
use App\Modules\Inventory\Application\ConfigureStock;
use App\Modules\Inventory\Contracts\StockStatus;
use App\Modules\Stores\Infrastructure\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Importa artículos desde la hoja del protocolo (docs/protocolo/README.md) y sus fotos.
 *
 * Sin $apply solo comprueba: hace todo dentro de una transacción que se deshace,
 * así el informe dice exactamente lo que pasaría. Con errores no se aplica nada.
 * Se puede repetir con la misma hoja: actualiza, no duplica.
 *
 * @phpstan-import-type ProductDraft from RowParser
 * @phpstan-import-type VariantDraft from RowParser
 * @phpstan-import-type GarmentDraft from GarmentParser
 * @phpstan-import-type SizeDraft from GarmentParser
 *
 * @phpstan-type Photo array{nombre: string, ruta: string, color: ?string, n: int}
 * @phpstan-type StockTask array{product_id: int, existed: bool, modelo: string, fila: int, venta: string, unidades: ?int, aviso: ?int}
 */
class CatalogImporter
{
    public const PHOTO_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public const DISK = 'public';

    /** @var array<string, int> */
    private array $garments = [];

    /** @var array<string, int> */
    private array $collections = [];

    /** @var list<array{0: string, 1: string}> ruta local => ruta en el disco */
    private array $filesToStore = [];

    /** @var list<string> */
    private array $filesToDelete = [];

    /** @var list<StockTask> */
    private array $stockTasks = [];

    public function __construct(
        private readonly SheetReader $reader,
        private readonly RowParser $parser,
        private readonly GarmentParser $garmentParser,
        private readonly PublicationCheck $publicationCheck,
        private readonly ConfigureStock $configureStock,
    ) {}

    public function import(string $sheetPath, ?PhotoSet $photos = null, bool $apply = false, ?int $userId = null, ?string $extension = null): ImportReport
    {
        $this->garments = $this->collections = $this->filesToStore = $this->filesToDelete = $this->stockTasks = [];
        $report = new ImportReport;

        try {
            $book = $this->reader->readAll($sheetPath, $extension);
        } catch (Throwable $e) {
            $report->error(null, 'hoja', 'No se puede leer la hoja: '.$e->getMessage());

            return $report;
        }
        $report->summary['filas'] = count($book['articulos']);

        $garments = $this->garmentParser->garments($book['prendas'], $report);
        $sizes = $this->garmentParser->sizes($book['tallas'], $report);
        $products = $this->parser->parse($book['articulos'], $report);
        $stores = $this->checkDatabase($products, $report);
        $garmentStore = $this->garmentStore($stores, $garments + $sizes !== [], $report);
        $this->checkGarments($garmentStore, $garments, $sizes, $report);
        $plan = $photos !== null ? $this->matchPhotos($products, $photos, $report) : [];

        if ($report->hasErrors()) {
            return $report;
        }

        DB::beginTransaction();
        try {
            if ($garmentStore !== null) {
                $this->saveGarments($garmentStore, $garments, $sizes, $report);
            }
            $position = 0;
            foreach ($products as $key => $draft) {
                $this->saveProduct($draft, $stores[$draft['tienda']], $plan[$key] ?? null, $photos !== null, $position++, $report);
            }
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            $report->error(null, 'hoja', 'La importación no se ha podido completar y no se ha cambiado nada: '.$e->getMessage());

            return $report;
        }

        // Todo o nada: un artículo que no se puede publicar para la importación entera.
        if ($report->hasErrors()) {
            DB::rollBack();

            return $report;
        }

        if (! $apply) {
            DB::rollBack();
            $this->checkStock($report, simulate: true, userId: $userId);

            return $report;
        }

        DB::commit();
        $report->applied = true;

        // Lo que vive fuera de la base de datos o nace al confirmar (fichas de stock) va después.
        foreach ($this->filesToStore as [$local, $destination]) {
            if (! Storage::disk(self::DISK)->exists($destination)) {
                Storage::disk(self::DISK)->put($destination, (string) file_get_contents($local));
            }
        }
        Storage::disk(self::DISK)->delete($this->filesToDelete);
        $this->checkStock($report, simulate: false, userId: $userId);

        return $report;
    }

    /**
     * Las tallas de la guía deben ser de una prenda de la pestaña Prendas o ya existente.
     *
     * @param  array<string, GarmentDraft>  $garments
     * @param  array<string, list<SizeDraft>>  $sizes
     */
    private function checkGarments(?Store $store, array $garments, array $sizes, ImportReport $report): void
    {
        if ($store === null) {
            return;
        }
        $known = BaseGarment::query()->where('store_id', $store->id)->whereIn('code', array_keys($sizes + $garments))->pluck('code')->all();
        foreach ($garments as $code => $garment) {
            if ($garment['nombre'] === null && ! in_array($code, $known, true)) {
                $report->error($garment['fila'], 'Prendas · nombre', "La prenda {$code} es nueva: necesita nombre.");
            }
        }
        foreach ($sizes as $code => $rows) {
            if (! isset($garments[$code]) && ! in_array($code, $known, true)) {
                $report->error($rows[0]['fila'], 'Tallas · prenda', "La prenda {$code} no existe ni está en la pestaña Prendas.");
            }
        }
    }

    /**
     * Ficha textil y guía de tallas. En Prendas, una celda vacía no cambia el dato;
     * en Tallas, la guía de cada prenda que aparece se sustituye entera.
     *
     * @param  array<string, GarmentDraft>  $garments
     * @param  array<string, list<SizeDraft>>  $sizes
     */
    private function saveGarments(Store $store, array $garments, array $sizes, ImportReport $report): void
    {
        foreach ($garments as $code => $draft) {
            $garment = BaseGarment::query()->where('store_id', $store->id)->firstWhere('code', $code);
            $isNew = $garment === null;
            $garment ??= new BaseGarment(['store_id' => $store->id, 'code' => $code]);
            $garment->fill(array_filter([
                'name' => $draft['nombre'],
                'material' => $draft['tejido'],
                'composition' => $draft['composicion'],
                'fit' => $draft['corte'],
                'care' => $draft['cuidados'],
                'origin_country' => $draft['pais'],
                'weight_grams' => $draft['peso'],
                'size_guide_note' => $draft['nota_tallas'],
            ], fn ($v): bool => $v !== null));
            if ($isNew) {
                $report->count('prendas_nuevas');
            } elseif ($garment->isDirty()) {
                $report->count('prendas_actualizadas');
            }
            $garment->save();
        }

        foreach ($sizes as $code => $rows) {
            $garment = BaseGarment::query()->where('store_id', $store->id)->where('code', $code)->firstOrFail();
            $garment->sizes()->delete();
            foreach ($rows as $position => $row) {
                $garment->sizes()->create([
                    'size' => $row['talla'],
                    'position' => $position,
                    'chest_cm' => $row['pecho'],
                    'length_cm' => $row['largo'],
                    'sleeve_cm' => $row['manga'],
                ]);
            }
            $report->count('tallas_guia', count($rows));
        }
    }

    /**
     * Tiendas, SKU y direcciones que ya existen en la base de datos.
     *
     * @param  array<string, ProductDraft>  $products
     * @return array<string, Store>
     */
    private function checkDatabase(array &$products, ImportReport $report): array
    {
        $stores = Store::query()->whereIn('subdomain', array_unique(array_column($products, 'tienda')))->get()->keyBy('subdomain')->all();

        foreach ($products as $key => &$draft) {
            $store = $stores[$draft['tienda']] ?? null;
            if ($store === null) {
                $report->error($draft['fila'], 'tienda', "No existe ninguna tienda con el subdominio \"{$draft['tienda']}\". Créala antes en el panel.");
                unset($products[$key]);

                continue;
            }

            foreach ($draft['variantes'] as &$variant) {
                $variant['sku'] ??= Sku::suggest($store->subdomain, $draft['modelo'], $variant['talla'], $variant['color']);
            }
            unset($variant);

            $existing = $this->findProduct($store, $draft);
            $slug = $this->slug($draft);
            if ($existing === null && Product::withTrashed()->where('store_id', $store->id)->where('slug', $slug)->exists()) {
                $report->error($draft['fila'], 'nombre', "Otro producto de la tienda ya usa la dirección \"{$slug}\".");
            }

            $taken = ProductVariant::query()
                ->whereIn('sku', array_column($draft['variantes'], 'sku'))
                ->when($existing, fn ($q) => $q->where('product_id', '!=', $existing->id))
                ->pluck('sku')->all();
            foreach ($draft['variantes'] as $variant) {
                if (in_array($variant['sku'], $taken, true)) {
                    $report->error($variant['fila'], 'sku', "El SKU {$variant['sku']} ya pertenece a otro producto.");
                }
            }
        }
        unset($draft);

        $seen = [];
        foreach ($products as $draft) {
            foreach ($draft['variantes'] as $variant) {
                if (isset($seen[$variant['sku']])) {
                    $report->error($variant['fila'], 'sku', "El SKU {$variant['sku']} sale repetido (fila {$seen[$variant['sku']]}). Pon un SKU propio a una de las dos filas.");
                }
                $seen[$variant['sku']] = $variant['fila'];
            }
        }

        return $stores;
    }

    /**
     * Asigna cada foto (MODELO_NN.ext o MODELO_COLOR_NN.ext) a su artículo.
     *
     * @param  array<string, ProductDraft>  $products
     * @return array<string, list<Photo>>
     */
    private function matchPhotos(array $products, PhotoSet $photos, ImportReport $report): array
    {
        $byModel = [];
        foreach ($products as $key => $draft) {
            $byModel[$draft['modelo']][] = $key;
        }

        foreach ($photos->duplicates as $name) {
            $report->error(null, 'fotos', "La foto {$name} está repetida en el ZIP.");
        }

        $plan = [];
        foreach ($photos->files as $name => $path) {
            $extension = Str::lower(pathinfo($name, PATHINFO_EXTENSION));
            if (! in_array($extension, self::PHOTO_EXTENSIONS, true)) {
                $report->warning(null, 'fotos', "{$name}: no es una foto JPG, PNG o WEBP, se ignora.");

                continue;
            }
            if (! preg_match('/^([A-Za-z0-9-]+)(?:_([A-Za-z0-9-]+))?_(\d{1,3})\.[A-Za-z]+$/', $name, $m)) {
                $report->error(null, 'fotos', "{$name}: el nombre no sigue el patrón MODELO_NN o MODELO_COLOR_NN (por ejemplo FQ-OS-01_NEGRO_01.jpg).");

                continue;
            }
            [, $modelo, $colorToken, $n] = $m;
            $keys = $byModel[Str::upper($modelo)] ?? [];
            if ($keys === []) {
                $report->error(null, 'fotos', "{$name}: el modelo ".Str::upper($modelo).' no está en la hoja.');

                continue;
            }
            if (count($keys) > 1) {
                $report->error(null, 'fotos', "{$name}: el modelo ".Str::upper($modelo).' está en varias tiendas de la hoja. Importa cada tienda por separado.');

                continue;
            }
            if ($photos->size($name) > PhotoSet::MAX_BYTES) {
                $report->error(null, 'fotos', "{$name}: pesa más de 8 MB.");

                continue;
            }

            $key = $keys[0];
            $color = null;
            if ($colorToken !== '') {
                foreach ($products[$key]['variantes'] as $variant) {
                    if ($variant['color'] !== null && Str::slug($variant['color']) === Str::slug($colorToken)) {
                        $color = $variant['color'];
                    }
                }
                if ($color === null) {
                    $report->error(null, 'fotos', "{$name}: el modelo {$products[$key]['modelo']} no tiene el color \"{$colorToken}\" en la hoja.");

                    continue;
                }
            }
            if ((int) $n === 0 && $color === null) {
                $report->error(null, 'fotos', "{$name}: el número 00 es la muestra de tejido de un color; pon el color (MODELO_COLOR_00).");

                continue;
            }
            $plan[$key][] = ['nombre' => $name, 'ruta' => $path, 'color' => $color, 'n' => (int) $n];
        }

        // Orden: primero las fotos generales y después las de cada color, en el orden de la hoja.
        foreach ($plan as $key => &$list) {
            $colors = array_values(array_unique(array_filter(array_column($products[$key]['variantes'], 'color'))));
            usort($list, fn (array $a, array $b): int => [$a['color'] === null ? -1 : (int) array_search($a['color'], $colors, true), $a['n'], $a['nombre']]
                <=> [$b['color'] === null ? -1 : (int) array_search($b['color'], $colors, true), $b['n'], $b['nombre']]);
        }
        unset($list);

        return $plan;
    }

    /**
     * @param  ProductDraft  $draft
     * @param  list<Photo>|null  $photos
     */
    private function saveProduct(array $draft, Store $store, ?array $photos, bool $photosGiven, int $position, ImportReport $report): void
    {
        $product = $this->findProduct($store, $draft);
        $isNew = $product === null;
        $product ??= new Product(['store_id' => $store->id, 'slug' => $this->slug($draft), 'status' => ProductStatus::Draft]);

        $minPrice = min(array_column($draft['variantes'], 'precio'));
        $product->fill([
            'reference' => $draft['modelo'],
            'name' => $draft['nombre'],
            'base_garment_id' => $draft['prenda'] === null ? null : $this->garment($store, $draft['prenda'], $report),
            'description' => $draft['descripcion'],
            'model_info' => $draft['info_modelo'],
            'price_cents' => $minPrice,
            'vat_rate_bps' => $draft['iva'],
            'commission_bps' => $draft['comision'],
            'lead_time_min_days' => $draft['plazo'][0],
            'lead_time_max_days' => $draft['plazo'][1],
            'personalized' => $draft['personalizado'],
        ]);
        if ($draft['estado'] !== null) {
            $product->status = $draft['estado'];
        }
        $changed = $product->isDirty();
        if ($product->trashed()) {
            $product->deleted_at = null;
            $changed = true;
            $report->warning($draft['fila'], 'modelo', "El modelo {$draft['modelo']} estaba eliminado: se recupera.");
        }
        $product->save();

        if ($draft['colecciones'] !== null) {
            $ids = [];
            foreach ($draft['colecciones'] as $name) {
                $ids[$this->collection($store, $name, $report)] = ['position' => $position];
            }
            $current = $product->collections()->pluck('collections.id')->sort()->values()->all();
            $product->collections()->sync($ids);
            $changed = $changed || $current !== collect(array_keys($ids))->sort()->values()->all();
        }

        $changed = $this->saveVariants($product, $draft, $minPrice, $report) || $changed;
        $this->stockTasks[] = $this->stockTask($product->id, ! $isNew, $draft, $report);

        if ($photos !== null) {
            $changed = $this->savePhotos($product, $store, $draft, $photos, $report) || $changed;
        } elseif (! $product->images()->exists()) {
            $report->warning($draft['fila'], 'fotos', "El modelo {$draft['modelo']} no tiene fotos".($photosGiven ? ' en el ZIP.' : '.'));
        }

        if ($product->status === ProductStatus::Published) {
            foreach ($this->publicationCheck->problems($product) as $problem) {
                $report->error($draft['fila'], 'estado', "El modelo {$draft['modelo']} no se puede publicar: {$problem}");
            }
        }

        if ($isNew) {
            $report->count('productos_nuevos');
        } elseif ($changed) {
            $report->count('productos_actualizados');
        } else {
            $report->count('productos_sin_cambios');
        }
    }

    /**
     * @param  ProductDraft  $draft
     */
    private function saveVariants(Product $product, array $draft, int $minPrice, ImportReport $report): bool
    {
        $changed = false;
        foreach ($draft['variantes'] as $position => $row) {
            $variant = ProductVariant::query()->where('sku', $row['sku'])->first();
            $existed = $variant !== null;
            $variant ??= new ProductVariant(['product_id' => $product->id, 'sku' => $row['sku']]);
            $variant->fill([
                'size' => $row['talla'],
                'color' => $row['color'],
                'color_hex' => $row['color_hex'],
                'price_cents' => $row['precio'] === $minPrice ? null : $row['precio'],
                'position' => $position,
                'active' => true,
            ]);

            if (! $existed) {
                $report->count('variantes_nuevas');
            } elseif ($variant->isDirty()) {
                $report->count('variantes_actualizadas');
                $changed = true;
            }
            $variant->save();
        }

        $missing = $product->variants()->where('active', true)->whereNotIn('sku', array_column($draft['variantes'], 'sku'))->pluck('sku');
        if ($missing->isNotEmpty()) {
            ProductVariant::query()->whereIn('sku', $missing)->update(['active' => false]);
            $report->count('variantes_desactivadas', $missing->count());
            $report->warning($draft['fila'], 'talla', "Se desactivan variantes del modelo {$draft['modelo']} que ya no están en la hoja: ".$missing->implode(', ').'.');
            $changed = true;
        }

        return $changed;
    }

    /**
     * Las fotos del ZIP sustituyen a las del producto. Ruta con huella del contenido:
     * una foto nueva cambia de dirección (sin cachés viejas) y la misma foto no se duplica.
     *
     * @param  ProductDraft  $draft
     * @param  list<Photo>  $photos
     */
    private function savePhotos(Product $product, Store $store, array $draft, array $photos, ImportReport $report): bool
    {
        $folder = 'products/'.$store->subdomain.'/'.Str::lower($draft['modelo']).'/';
        $new = [];
        foreach ($photos as $photo) {
            $extension = Str::lower(pathinfo($photo['nombre'], PATHINFO_EXTENSION));
            $hash = substr((string) sha1_file($photo['ruta']), 0, 10);
            $path = $folder.Str::lower(pathinfo($photo['nombre'], PATHINFO_FILENAME)).'-'.$hash.'.'.($extension === 'jpeg' ? 'jpg' : $extension);
            $swatch = $photo['n'] === 0;
            $new[] = [
                'path' => $path,
                'color' => $photo['color'],
                'is_swatch' => $swatch,
                'alt' => trim(($swatch ? 'Muestra de tejido · ' : '').$draft['nombre'].' '.($photo['color'] ?? '')),
            ];
            $this->filesToStore[] = [$photo['ruta'], $path];
        }
        $report->count('fotos', count($new));

        $current = $product->images()->get(['path', 'color', 'is_swatch', 'alt'])->map->only(['path', 'color', 'is_swatch', 'alt'])->all();
        if ($current === $new) {
            return false;
        }

        foreach (array_diff(array_column($current, 'path'), array_column($new, 'path')) as $old) {
            if (str_starts_with($old, $folder)) {
                $this->filesToDelete[] = $old; // solo las que puso el importador
            }
        }
        $product->images()->delete();
        foreach ($new as $position => $image) {
            $product->images()->create($image + ['position' => $position]);
        }

        return true;
    }

    /**
     * Inventario del producto (ADR 0022) a partir de sus filas: «agotado» manda sobre
     * «limitada», y esta sobre «bajo pedido». Las unidades son del producto entero.
     *
     * @param  ProductDraft  $draft
     * @return StockTask
     */
    private function stockTask(int $productId, bool $existed, array $draft, ImportReport $report): array
    {
        $ventas = array_column($draft['variantes'], 'venta');
        $venta = in_array('agotado', $ventas, true) ? 'agotado' : (in_array('limitada', $ventas, true) ? 'limitada' : 'bajo-pedido');
        $unidades = array_values(array_unique(array_filter(array_column($draft['variantes'], 'unidades'), fn (?int $u): bool => $u !== null)));
        if (count($unidades) > 1) {
            $report->warning($draft['fila'], 'unidades', "El modelo {$draft['modelo']} tiene unidades distintas en sus filas; se usa {$unidades[0]} para el producto entero.");
        }
        $avisos = array_values(array_filter(array_column($draft['variantes'], 'aviso'), fn (?int $a): bool => $a !== null));

        return [
            'product_id' => $productId, 'existed' => $existed, 'modelo' => $draft['modelo'], 'fila' => $draft['fila'],
            'venta' => $venta, 'unidades' => $unidades[0] ?? null, 'aviso' => $avisos[0] ?? null,
        ];
    }

    /**
     * Inventario de cada producto, con las mismas reglas que la ficha del panel (ConfigureStock):
     * las unidades no pisan un inventario que ya tiene ventas.
     */
    private function checkStock(ImportReport $report, bool $simulate, ?int $userId): void
    {
        foreach ($this->stockTasks as $task) {
            if ($simulate && ! $task['existed']) {
                continue; // el producto nace al confirmar
            }
            $limited = $task['venta'] === 'limitada';
            $warning = ($this->configureStock)(
                $task['product_id'],
                $task['venta'] === 'agotado' ? StockStatus::OutOfStock : StockStatus::InStock,
                $limited,
                $limited ? $task['unidades'] : null,
                $limited ? $task['aviso'] : null,
                userId: $userId,
                simulate: $simulate,
            );
            if ($warning !== null) {
                $report->warning($task['fila'], 'unidades', "{$task['modelo']} {$warning}");
            }
        }
    }

    /**
     * @param  ProductDraft  $draft
     */
    private function findProduct(Store $store, array $draft): ?Product
    {
        return Product::withTrashed()->where('store_id', $store->id)->where('reference', $draft['modelo'])->first()
            ?? Product::withTrashed()->where('store_id', $store->id)->whereNull('reference')->where('slug', $this->slug($draft))->first();
    }

    /**
     * @param  ProductDraft  $draft
     */
    private function slug(array $draft): string
    {
        return Str::slug($draft['nombre'].' '.$draft['modelo']);
    }

    /**
     * Las pestañas Prendas y Tallas son de la tienda de la hoja: una hoja, una tienda.
     *
     * @param  array<string, Store>  $stores
     */
    private function garmentStore(array $stores, bool $hasGarmentTabs, ImportReport $report): ?Store
    {
        if (count($stores) === 1) {
            return reset($stores);
        }
        if ($hasGarmentTabs && count($stores) > 1) {
            $report->error(null, 'Prendas', 'Las pestañas Prendas y Tallas son de una sola tienda: prepara una hoja por tienda.');
        }

        return null;
    }

    /** Prenda base de la tienda por código o por nombre; si no existe, se crea en esa tienda. */
    private function garment(Store $store, string $value, ImportReport $report): int
    {
        $key = $store->id.'|'.Str::lower($value);
        if (isset($this->garments[$key])) {
            return $this->garments[$key];
        }

        $inStore = fn () => BaseGarment::query()->where('store_id', $store->id);
        $garment = $inStore()->where('code', Str::upper($value))->first()
            ?? $inStore()->whereRaw('LOWER(name) = ?', [Str::lower($value)])->first();

        if ($garment === null) {
            $code = Str::upper(Str::limit(Str::slug($value), 24, ''));
            $base = $code;
            for ($i = 2; $inStore()->where('code', $code)->exists(); $i++) {
                $code = $base.'-'.$i;
            }
            $garment = BaseGarment::query()->create(['store_id' => $store->id, 'code' => $code, 'name' => $value]);
            $report->count('prendas_nuevas');
        }

        return $this->garments[$key] = $garment->id;
    }

    private function collection(Store $store, string $name, ImportReport $report): int
    {
        $slug = Str::slug($name);
        $key = $store->id.'|'.$slug;
        if (isset($this->collections[$key])) {
            return $this->collections[$key];
        }

        $collection = Collection::query()->where('store_id', $store->id)->where('slug', $slug)->first();
        if ($collection === null) {
            $collection = Collection::query()->create([
                'store_id' => $store->id,
                'name' => $name,
                'position' => (int) Collection::query()->where('store_id', $store->id)->max('position') + 1,
            ]);
            $report->count('colecciones_nuevas');
        }

        return $this->collections[$key] = $collection->id;
    }
}
