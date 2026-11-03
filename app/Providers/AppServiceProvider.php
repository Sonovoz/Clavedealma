<?php

namespace App\Providers;

use App\Modules\Catalog\Infrastructure\Models\BaseGarment;
use App\Modules\Catalog\Infrastructure\Models\Collection;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Catalog\Infrastructure\Models\ProductImage;
use App\Modules\Catalog\Infrastructure\Models\ProductVariant;
use App\Modules\Inventory\Infrastructure\Models\StockLevel;
use App\Modules\Inventory\Infrastructure\Models\StockMovement;
use App\Modules\Stores\Infrastructure\Models\Artist;
use App\Modules\Stores\Infrastructure\Models\Store;
use App\Policies\StockLevelPolicy;
use App\Policies\StockMovementPolicy;
use App\Policies\SuperadminOnlyPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // En desarrollo y en las pruebas, error ante consultas N+1 o atributos inexistentes.
        Model::shouldBeStrict($this->app->isLocal() || $this->app->runningUnitTests());

        // Tiendas y catálogo: solo superadministradores.
        foreach ([Artist::class, Store::class, BaseGarment::class, Product::class, ProductVariant::class, ProductImage::class, Collection::class] as $model) {
            Gate::policy($model, SuperadminOnlyPolicy::class);
        }

        Gate::policy(StockLevel::class, StockLevelPolicy::class);
        Gate::policy(StockMovement::class, StockMovementPolicy::class);
    }
}
