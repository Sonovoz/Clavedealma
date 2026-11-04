<?php

namespace App\Providers;

use App\Filament\Livewire\StoreSwitcher;
use App\Modules\Catalog\Infrastructure\Models\BaseGarment;
use App\Modules\Catalog\Infrastructure\Models\Collection;
use App\Modules\Catalog\Infrastructure\Models\GarmentSize;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Catalog\Infrastructure\Models\ProductImage;
use App\Modules\Catalog\Infrastructure\Models\ProductVariant;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Inventory\Infrastructure\Models\StockLevel;
use App\Modules\Inventory\Infrastructure\Models\StockMovement;
use App\Modules\Shipping\Infrastructure\Models\Carrier;
use App\Modules\Shipping\Infrastructure\Models\RateBand;
use App\Modules\Shipping\Infrastructure\Models\ZoneRate;
use App\Modules\Stores\Infrastructure\Models\Artist;
use App\Modules\Stores\Infrastructure\Models\Store;
use App\Policies\CatalogPolicy;
use App\Policies\StockLevelPolicy;
use App\Policies\StockMovementPolicy;
use App\Policies\SuperadminOnlyPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Livewire::component('store-switcher', StoreSwitcher::class);

        // En desarrollo y en las pruebas, error ante consultas N+1 o atributos inexistentes.
        Model::shouldBeStrict($this->app->isLocal() || $this->app->runningUnitTests());

        // Artistas, tiendas, usuarios y envíos (logística de Clave de Alma): solo superadministradores.
        foreach ([Artist::class, Store::class, User::class, Carrier::class, ZoneRate::class, RateBand::class] as $model) {
            Gate::policy($model, SuperadminOnlyPolicy::class);
        }

        // Catálogo de todas las tiendas: superadministradores y gestores de catálogo.
        foreach ([BaseGarment::class, GarmentSize::class, Product::class, ProductVariant::class, ProductImage::class, Collection::class] as $model) {
            Gate::policy($model, CatalogPolicy::class);
        }

        Gate::policy(StockLevel::class, StockLevelPolicy::class);
        Gate::policy(StockMovement::class, StockMovementPolicy::class);
    }
}
