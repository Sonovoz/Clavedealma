<?php

namespace Database\Seeders;

use App\Modules\Catalog\Infrastructure\Models\BaseGarment;
use App\Modules\Catalog\Infrastructure\Models\Collection;
use App\Modules\Stores\Contracts\StoreStatus;
use App\Modules\Stores\Infrastructure\Models\Artist;
use App\Modules\Stores\Infrastructure\Models\Store;
use App\Platform\CatalogImport\CatalogImporter;
use App\Platform\CatalogImport\PhotoSet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Primera tienda de prueba: Farruquito, con sus colecciones On Stage, Artist Voice e Iconic.
 *
 *   php artisan db:seed --class=FarruquitoSeeder
 *
 * Crea el artista, la tienda (OCULTA), las prendas base y las colecciones, y da de alta
 * los artículos con el importador del protocolo, igual que se hará con los reales:
 * database/seeders/data/farruquito/articulos.xlsx + fotos/ (ver docs/protocolo).
 *
 * Los PRECIOS, la COMISIÓN y las UNIDADES son de ejemplo, para ver el sistema funcionando.
 * Se puede ejecutar varias veces: no duplica nada. No se ejecuta en producción.
 */
class FarruquitoSeeder extends Seeder
{
    /** @var array<string, array{0: string, 1: string}> */
    private const PRENDAS = [
        'FQ-CAM-OS' => ['Camiseta oversize 280 g', 'Algodón 280 g/m² · 100 % natural'],
        'FQ-CAM-BOXY' => ['Camiseta boxy oversize', 'Algodón premium'],
        'FQ-CAM-REG' => ['Camiseta regular fit', 'Algodón premium de tacto suave'],
        'FQ-SUD-FELPA' => ['Sudadera de felpa 280 g', 'Felpa 280 g/m² · 100 % algodón'],
        'FQ-SUD-CREW' => ['Sudadera crewneck', 'Felpa de algodón premium'],
        'FQ-SUD-HOOD' => ['Sudadera hoodie', 'Felpa de algodón premium'],
        'FQ-GOR-DAD' => ['Gorra dad cap', 'Algodón · cierre ajustable'],
        'FQ-TOTE' => ['Tote bag', 'Canvas de algodón 340 g/m²'],
    ];

    /** @var array<string, array{0: string, 1: string}> nombre y descripción de cada colección */
    private const COLECCIONES = [
        'onstage' => ['On Stage', 'Serás Farruquito · El Musical. Dos lenguajes, una misma verdad: el arte hecho legado.'],
        'artistvoice' => ['Artist Voice', 'Expresa. Crea. Trasciende. Tu voz es tu legado.'],
        'iconic' => ['Iconic', 'Built on heritage. Made to be iconic.'],
    ];

    public function run(CatalogImporter $importer): void
    {
        if (app()->isProduction()) {
            $this->command?->error('FarruquitoSeeder usa datos de ejemplo y no se ejecuta en producción.');

            return;
        }

        DB::transaction(function (): void {
            $artist = Artist::query()->firstOrCreate(['display_name' => 'Farruquito'], [
                'country' => 'ES',
                'notes' => 'Datos fiscales y acuerdo de autofacturación pendientes.',
            ]);

            $store = Store::query()->firstOrCreate(['subdomain' => 'farruquito'], [
                'artist_id' => $artist->id,
                'name' => 'Farruquito',
                'status' => StoreStatus::Hidden,
                'commission_bps' => 2500, // ejemplo: pendiente del acuerdo con el artista
                'tagline' => 'Tienda oficial · Since 1982',
                'bio' => 'Tienda oficial de Farruquito en Clave de Alma: colecciones On Stage, Artist Voice e Iconic.',
            ]);

            foreach (self::PRENDAS as $codigo => [$nombre, $material]) {
                BaseGarment::query()->firstOrCreate(['code' => $codigo], ['name' => $nombre, 'material' => $material]);
            }

            $orden = 0;
            foreach (self::COLECCIONES as [$nombre, $descripcion]) {
                Collection::query()->firstOrCreate(
                    ['store_id' => $store->id, 'slug' => Str::slug($nombre)],
                    ['name' => $nombre, 'description' => $descripcion, 'position' => $orden++],
                );
            }
        });

        $datos = database_path('seeders/data/farruquito');
        $report = $importer->import($datos.'/articulos.xlsx', PhotoSet::fromDirectory($datos.'/fotos'), apply: true);

        if ($report->hasErrors()) {
            throw new RuntimeException('La hoja de Farruquito tiene errores: '.json_encode($report->errors, JSON_UNESCAPED_UNICODE));
        }

        $this->command?->info(sprintf(
            'Tienda Farruquito (oculta): %d productos nuevos, %d actualizados, %d fotos.',
            $report->summary['productos_nuevos'], $report->summary['productos_actualizados'], $report->summary['fotos'],
        ));
    }
}
