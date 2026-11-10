<?php

namespace Database\Seeders;

use App\Modules\Catalog\Infrastructure\Models\Collection;
use App\Modules\Stores\Contracts\StoreStatus;
use App\Modules\Stores\Infrastructure\Models\Artist;
use App\Modules\Stores\Infrastructure\Models\Store;
use App\Platform\CatalogImport\CatalogImporter;
use App\Platform\CatalogImport\PhotoSet;
use App\Storefront\Seo\SeoBuilder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Primera tienda de prueba: Farruquito, con sus colecciones On Stage, Artist Voice e Iconic.
 *
 *   php artisan db:seed --class=FarruquitoSeeder
 *
 * Crea el artista, la tienda (OCULTA) y las colecciones, y da de alta prendas base y
 * artículos con el importador del protocolo, igual que se hará con los reales:
 * database/seeders/data/farruquito/articulos.xlsx (pestañas Artículos y Prendas) + fotos/.
 *
 * PRECIOS, COMISIÓN, UNIDADES, COMPOSICIÓN y CUIDADOS son de ejemplo, para ver el sistema
 * funcionando. La guía de tallas queda vacía hasta tener las medidas reales.
 * Se puede ejecutar varias veces: no duplica nada.
 *
 * En el servidor solo durante la fase de pruebas (SEO_INDEXING=false): cuando la web está
 * lanzada y Google la indexa, se niega, para que nunca entren datos de ejemplo en la tienda real.
 */
class FarruquitoSeeder extends Seeder
{
    /** @var array<string, array{0: string, 1: string}> nombre y descripción de cada colección */
    private const COLECCIONES = [
        'onstage' => ['On Stage', 'Serás Farruquito · El Musical. Dos lenguajes, una misma verdad: el arte hecho legado.'],
        'artistvoice' => ['Artist Voice', 'Expresa. Crea. Trasciende. Tu voz es tu legado.'],
        'iconic' => ['Iconic', 'Built on heritage. Made to be iconic.'],
    ];

    public function run(CatalogImporter $importer): void
    {
        if (SeoBuilder::indexing()) {
            $this->command?->error('FarruquitoSeeder usa datos de ejemplo: solo se ejecuta mientras la web está en pruebas (SEO_INDEXING=false), no con la web lanzada.');

            return;
        }
        if (app()->isProduction()) {
            $this->command?->warn('Servidor en fase de pruebas: se crea la tienda Farruquito OCULTA con precios de ejemplo. Revísalos antes de publicarla.');
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
                'design' => 'farruquito',
                'theme' => ['genres' => ['flamenco']],
            ]);
            if ($store->design === 'base') {
                $store->update(['design' => 'farruquito', 'theme' => ['genres' => ['flamenco']] + (array) $store->theme]);
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
