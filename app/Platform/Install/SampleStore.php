<?php

namespace App\Platform\Install;

use App\Modules\Stores\Contracts\StoreStatus;
use App\Modules\Stores\Infrastructure\Models\Store;
use App\Storefront\Seo\SeoBuilder;
use Database\Seeders\FarruquitoSeeder;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

/**
 * La tienda de ejemplo Farruquito (precios de ejemplo), creada desde el instalador o desde
 * «Estado del sistema» sin usar la línea de comandos. Solo mientras la web está en pruebas
 * (SEO_INDEXING=false): con la web lanzada no se ofrece, igual que el propio seeder.
 */
class SampleStore
{
    public const KEY = 'farruquito';

    public static function available(): bool
    {
        return ! SeoBuilder::indexing() && is_file(database_path('seeders/data/farruquito/articulos.xlsx'));
    }

    public static function exists(): bool
    {
        return Store::query()->where('subdomain', self::KEY)->where('status', StoreStatus::Published)->exists();
    }

    /** Crea (o completa y abre) la tienda. Se puede repetir: no duplica nada. */
    public function create(): void
    {
        if (! self::available()) {
            throw new RuntimeException('La tienda de ejemplo solo se crea mientras la web está en pruebas (SEO_INDEXING=false).');
        }
        @set_time_limit(300);

        if (Artisan::call('db:seed', ['--class' => FarruquitoSeeder::class, '--force' => true]) !== 0 || ! self::exists()) {
            throw new RuntimeException('No se ha podido crear la tienda de ejemplo: '.trim(Artisan::output()));
        }
    }
}
