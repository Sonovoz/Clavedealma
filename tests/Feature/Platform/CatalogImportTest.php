<?php

use App\Modules\Catalog\Contracts\ProductStatus;
use App\Modules\Catalog\Infrastructure\Models\BaseGarment;
use App\Modules\Catalog\Infrastructure\Models\Collection;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Catalog\Infrastructure\Models\ProductVariant;
use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Inventory\Application\AdjustStock;
use App\Modules\Inventory\Contracts\StockMovementReason;
use App\Modules\Inventory\Contracts\StockStatus;
use App\Modules\Inventory\Infrastructure\Models\ProductStock;
use App\Modules\Stores\Infrastructure\Models\Store;
use App\Platform\CatalogImport\CatalogImporter;
use App\Platform\CatalogImport\PhotoSet;
use App\Platform\CatalogImport\SheetReader;
use App\Platform\CatalogImport\TemplateWriter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    Store::factory()->create(['subdomain' => 'prueba']);
    $this->dir = sys_get_temp_dir().'/cda-import-'.uniqid();
    mkdir($this->dir);
});

afterEach(fn () => File::deleteDirectory($this->dir));

/**
 * @param  list<array<string, string>>  $rows
 */
function hoja(string $dir, array $rows, string $name = 'hoja.csv'): string
{
    $headers = array_keys(array_merge(...$rows));
    $lines = [implode(';', $headers)];
    foreach ($rows as $row) {
        $lines[] = implode(';', array_map(fn ($h) => '"'.str_replace('"', '""', $row[$h] ?? '').'"', $headers));
    }
    file_put_contents($dir.'/'.$name, implode("\n", $lines));

    return $dir.'/'.$name;
}

function camiseta(array $extra = []): array
{
    return [
        ['tienda' => 'prueba', 'coleccion' => 'Gira', 'modelo' => 'PR-01', 'nombre' => 'Camiseta gira', 'prenda' => 'Camiseta oversize', 'color' => 'Negro', 'talla' => 'S', 'precio' => '29,90', 'descripcion' => 'Primera línea', ...$extra],
        ['tienda' => 'prueba', 'modelo' => 'PR-01', 'color' => 'Negro', 'talla' => 'M', 'precio' => '29,90', ...$extra],
        ['tienda' => 'prueba', 'modelo' => 'PR-01', 'color' => 'Blanco', 'talla' => 'XXL', 'precio' => '32,90', ...$extra],
    ];
}

function importar(string $sheet, ?PhotoSet $photos = null, bool $apply = true)
{
    return app(CatalogImporter::class)->import($sheet, $photos, $apply);
}

it('comprueba sin guardar nada y luego importa', function () {
    $sheet = hoja($this->dir, camiseta());

    $check = importar($sheet, apply: false);
    expect($check->errors)->toBe([])
        ->and($check->applied)->toBeFalse()
        ->and($check->summary['productos_nuevos'])->toBe(1)
        ->and($check->summary['variantes_nuevas'])->toBe(3)
        ->and(Product::count())->toBe(0)
        ->and(Collection::count())->toBe(0);

    $report = importar($sheet);
    $product = Product::with('variants', 'collections', 'baseGarment')->sole();

    expect($report->applied)->toBeTrue()
        ->and($product->reference)->toBe('PR-01')
        ->and($product->slug)->toBe('camiseta-gira-pr-01')
        ->and($product->status)->toBe(ProductStatus::Draft)
        ->and($product->price_cents)->toBe(2990)
        ->and($product->description)->toBe('<p>Primera línea</p>')
        ->and($product->baseGarment?->name)->toBe('Camiseta oversize')
        ->and($product->collections->pluck('name')->all())->toBe(['Gira'])
        ->and($product->variants->pluck('sku')->all())->toBe(['PRUEBA-PR-01-S-NEGRO', 'PRUEBA-PR-01-M-NEGRO', 'PRUEBA-PR-01-XXL-BLANCO'])
        ->and($product->variants->pluck('price_cents')->all())->toBe([null, null, 3290])
        ->and(ProductStock::sole()->managed)->toBeFalse()
        ->and(ProductStock::sole()->status)->toBe(StockStatus::InStock);
});

it('se puede repetir sin duplicar y desactiva las variantes que salen de la hoja', function () {
    importar(hoja($this->dir, camiseta()));

    $again = importar(hoja($this->dir, camiseta()));
    expect($again->summary['productos_nuevos'])->toBe(0)
        ->and($again->summary['productos_actualizados'])->toBe(0)
        ->and($again->summary['productos_sin_cambios'])->toBe(1)
        ->and($again->summary['variantes_nuevas'])->toBe(0)
        ->and(ProductVariant::count())->toBe(3);

    $sinBlanco = array_slice(camiseta(), 0, 2);
    $report = importar(hoja($this->dir, $sinBlanco));

    expect($report->summary['variantes_desactivadas'])->toBe(1)
        ->and(ProductVariant::where('active', true)->count())->toBe(2)
        ->and(ProductVariant::count())->toBe(3);
});

it('informa de los errores fila a fila y no cambia nada', function () {
    $rows = camiseta();
    $rows[1]['nombre'] = 'Otro nombre';      // dato de producto distinto en el mismo modelo
    $rows[2]['precio'] = 'gratis';
    $rows[] = ['tienda' => 'noexiste', 'modelo' => 'X-1', 'nombre' => 'X', 'precio' => '10'];
    $rows[] = ['tienda' => 'prueba', 'modelo' => 'PR 02', 'nombre' => 'Mal', 'precio' => '10'];
    $rows[] = ['tienda' => 'prueba', 'modelo' => 'PR-01', 'color' => 'Negro', 'talla' => 's', 'precio' => '29,90'];
    $rows[] = ['tienda' => 'prueba', 'modelo' => 'PR-03', 'nombre' => 'Limitada', 'precio' => '10', 'venta' => 'limitada'];

    $report = importar(hoja($this->dir, $rows));

    expect($report->applied)->toBeFalse()
        ->and(Product::count())->toBe(0)
        ->and(collect($report->errors)->map(fn ($e) => $e['fila'].':'.$e['campo'])->all())
        ->toContain('3:nombre', '4:precio', '6:modelo', '7:talla', '8:unidades', '5:tienda');
});

it('asigna las fotos por modelo y color, y rechaza las que no encajan', function () {
    $fotos = [];
    foreach (['PR-01_01.jpg', 'PR-01_BLANCO_01.jpg', 'pr-01_negro_02.png', 'notas.txt'] as $name) {
        file_put_contents($fotos[$name] = $this->dir.'/'.$name, 'img-'.$name);
    }
    $report = importar(hoja($this->dir, camiseta()), PhotoSet::fromFiles($fotos));

    expect($report->errors)->toBe([])
        ->and($report->warnings)->toHaveCount(1); // notas.txt se ignora

    $images = Product::sole()->images()->get();
    expect($images->pluck('color')->all())->toBe([null, 'Negro', 'Blanco'])
        ->and($images->first()->path)->toStartWith('products/prueba/pr-01/pr-01_01-');
    Storage::disk('public')->assertExists($images->pluck('path')->all());

    $mal = ['PR-01_ROJO_01.jpg' => $fotos['PR-01_01.jpg'], 'XX-9_01.jpg' => $fotos['PR-01_01.jpg'], 'PR-01.jpg' => $fotos['PR-01_01.jpg']];
    $report = importar(hoja($this->dir, camiseta()), PhotoSet::fromFiles($mal));
    expect($report->errors)->toHaveCount(3)
        ->and(Product::sole()->images()->count())->toBe(3);
});

it('lee las fotos de un ZIP sin rutas peligrosas', function () {
    $zipPath = $this->dir.'/fotos.zip';
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::CREATE);
    $zip->addFromString('../../fuera/PR-01_01.jpg', 'a');
    $zip->addFromString('carpeta/PR-01_NEGRO_01.jpg', 'b');
    $zip->addFromString('__MACOSX/carpeta/._PR-01_NEGRO_01.jpg', 'x');
    $zip->addFromString('.DS_Store', 'x');
    $zip->close();

    $set = PhotoSet::fromZip($zipPath, $this->dir.'/tmp');

    expect(array_keys($set->files))->toBe(['PR-01_01.jpg', 'PR-01_NEGRO_01.jpg'])
        ->and(collect($set->files)->every(fn ($p) => str_starts_with($p, $this->dir.'/tmp/')))->toBeTrue();

    $set->cleanup();
    expect(is_dir($this->dir.'/tmp'))->toBeFalse();
});

it('la venta «limitada» fija las unidades del producto y no pisa lo vendido', function () {
    $rows = array_map(fn ($r) => $r + ['venta' => 'limitada', 'unidades' => '10', 'aviso_stock' => '2'], camiseta());
    importar(hoja($this->dir, $rows));

    $stock = ProductStock::sole();
    expect($stock->managed)->toBeTrue()
        ->and($stock->quantity)->toBe(10)
        ->and($stock->low_stock_threshold)->toBe(2);

    app(AdjustStock::class)($stock->product_id, -3, StockMovementReason::Sale, 'Pedido X');

    $report = importar(hoja($this->dir, array_map(fn ($r) => array_merge($r, ['unidades' => '50']), $rows)));

    expect($stock->fresh()->quantity)->toBe(7)
        ->and(collect($report->warnings)->pluck('campo'))->toContain('unidades');
});

it('la venta «agotado» deja el producto sin vender por ahora', function () {
    importar(hoja($this->dir, array_map(fn ($r) => $r + ['venta' => 'agotado'], camiseta())));

    expect(ProductStock::sole()->status)->toBe(StockStatus::OutOfStock)
        ->and(ProductStock::sole()->limit())->toBe(0);
});

it('lee la plantilla xlsx que genera el propio sistema', function () {
    $path = $this->dir.'/plantilla.xlsx';
    app(TemplateWriter::class)->write($path, camiseta(['plazo' => '5-7', 'precio' => '29,90']));

    $rows = app(SheetReader::class)->read($path);
    expect($rows)->toHaveCount(3)
        ->and($rows[0]['fila'])->toBe(2)
        ->and($rows[0]['datos']['plazo'])->toBe('5-7')
        ->and(importar($path)->errors)->toBe([]);
});

it('importa desde la consola (herramienta interna: el panel ya no tiene esta pantalla)', function () {
    $sheet = hoja($this->dir, camiseta());

    $this->artisan('catalogo:importar', ['hoja' => $sheet])->expectsOutputToContain('Comprobación correcta')->assertSuccessful();
    expect(Product::count())->toBe(0);

    $this->artisan('catalogo:importar', ['hoja' => $sheet, '--aplicar' => true])->expectsOutputToContain('Importación aplicada')->assertSuccessful();
    expect(Product::count())->toBe(1);
});

it('importa la ficha textil y la guía de tallas de las pestañas Prendas y Tallas', function () {
    $path = $this->dir.'/libro.xlsx';
    app(TemplateWriter::class)->write($path,
        camiseta(['prenda' => 'PR-CAM', 'info_modelo' => 'Mide 1,80 y lleva M']),
        [['codigo' => 'pr-cam', 'nombre' => 'Camiseta de prueba', 'composicion' => '100 % algodón', 'corte' => 'Oversize', 'cuidados' => 'Lavar a 30 °C']],
        [
            ['prenda' => 'PR-CAM', 'talla' => '2XL', 'pecho' => '64', 'largo' => '78', 'manga' => '25,5'],
            ['prenda' => 'PR-CAM', 'talla' => 'S', 'pecho' => '52', 'largo' => '70', 'manga' => '22'],
        ],
    );

    $report = importar($path);
    $garment = BaseGarment::where('code', 'PR-CAM')->with('sizes')->sole();

    expect($report->errors)->toBe([])
        ->and($report->summary['prendas_nuevas'])->toBe(1)
        ->and($report->summary['tallas_guia'])->toBe(2)
        ->and($garment->composition)->toBe('100 % algodón')
        ->and($garment->sizes->pluck('size')->all())->toBe(['S', 'XXL'])
        ->and($garment->sizes->last()->sleeve_cm)->toBe('25.5')
        ->and(Product::sole()->base_garment_id)->toBe($garment->id)
        ->and(Product::sole()->model_info)->toBe('Mide 1,80 y lleva M');
});

it('no importa nada si un artículo publicado está incompleto', function () {
    $rows = camiseta(['estado' => 'publicado']); // sin fotos y con prenda sin composición

    $report = importar(hoja($this->dir, $rows));

    expect(collect($report->errors)->pluck('campo')->unique()->all())->toBe(['estado'])
        ->and(Product::count())->toBe(0);
});

it('la foto 00 de un color es su muestra de tejido', function () {
    $fotos = [];
    foreach (['PR-01_01.jpg', 'PR-01_NEGRO_00.jpg', 'PR-01_00.jpg'] as $name) {
        file_put_contents($fotos[$name] = $this->dir.'/'.$name, 'img-'.$name);
    }

    $report = importar(hoja($this->dir, camiseta()), PhotoSet::fromFiles($fotos));
    expect(collect($report->errors)->pluck('mensaje')->implode(' '))->toContain('PR-01_00.jpg');

    unset($fotos['PR-01_00.jpg']);
    importar(hoja($this->dir, camiseta()), PhotoSet::fromFiles($fotos));

    $swatch = Product::sole()->images()->where('is_swatch', true)->sole();
    expect($swatch->color)->toBe('Negro');
});

it('unifica las tallas: 2XL y XXL son la misma', function () {
    $rows = camiseta();
    $rows[2]['talla'] = '2xl';
    $rows[] = ['tienda' => 'prueba', 'modelo' => 'PR-01', 'color' => 'Blanco', 'talla' => 'XXL', 'precio' => '32,90'];

    $report = importar(hoja($this->dir, $rows));

    expect(collect($report->errors)->pluck('campo')->all())->toBe(['talla']);
});

it('el panel ya no tiene la pantalla de importar', function () {
    $this->actingAs(User::factory()->withRole(Role::Superadmin)->create())
        ->get('/admin/importar-articulos')->assertNotFound();
});
