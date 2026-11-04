<?php

namespace App\Providers;

use App\Filament\Livewire\StoreSwitcher;
use App\Modules\Cart\Application\CartStore;
use App\Modules\Catalog\Infrastructure\Models\BaseGarment;
use App\Modules\Catalog\Infrastructure\Models\Collection;
use App\Modules\Catalog\Infrastructure\Models\GarmentSize;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Catalog\Infrastructure\Models\ProductImage;
use App\Modules\Catalog\Infrastructure\Models\ProductVariant;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Inventory\Infrastructure\Models\StockLevel;
use App\Modules\Inventory\Infrastructure\Models\StockMovement;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Orders\Infrastructure\Models\OrderEvent;
use App\Modules\Orders\Infrastructure\Models\OrderLine;
use App\Modules\Payments\Infrastructure\Models\Payment;
use App\Modules\Payments\Infrastructure\Models\Refund;
use App\Modules\Returns\Infrastructure\Models\ReturnLine;
use App\Modules\Returns\Infrastructure\Models\ReturnRequest;
use App\Modules\Shipping\Infrastructure\Models\Carrier;
use App\Modules\Shipping\Infrastructure\Models\RateBand;
use App\Modules\Shipping\Infrastructure\Models\ZoneRate;
use App\Modules\Stores\Infrastructure\Models\Artist;
use App\Modules\Stores\Infrastructure\Models\Store;
use App\Platform\Ai\Drivers\NullTextGenerator;
use App\Platform\Ai\Drivers\OpenAiCompatibleTextGenerator;
use App\Platform\Ai\TextGenerator;
use App\Platform\Backup\Backup;
use App\Platform\Pdf\DompdfRenderer;
use App\Platform\Pdf\PdfRenderer;
use App\Policies\CatalogPolicy;
use App\Policies\StockLevelPolicy;
use App\Policies\StockMovementPolicy;
use App\Policies\SuperadminOnlyPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Proveedor de IA según config/ai.php (contrato propio con driver).
        $this->app->singleton(TextGenerator::class, fn (): TextGenerator => match (config('ai.driver')) {
            'openai' => new OpenAiCompatibleTextGenerator((array) config('ai.openai')),
            default => new NullTextGenerator,
        });

        // Documentos PDF del panel (fabricación, albaranes e informes).
        $this->app->bind(PdfRenderer::class, DompdfRenderer::class);
    }

    public function boot(): void
    {
        Livewire::component('store-switcher', StoreSwitcher::class);

        // En desarrollo y en las pruebas, error ante consultas N+1 o atributos inexistentes.
        Model::shouldBeStrict($this->app->isLocal() || $this->app->runningUnitTests());

        // Artistas, tiendas, usuarios, envíos, pedidos y pagos: solo superadministradores
        // (el gestor de catálogo no ve datos de clientes ni de cobros).
        foreach ([Artist::class, Store::class, User::class, Carrier::class, ZoneRate::class, RateBand::class, Backup::class, Order::class, OrderLine::class, OrderEvent::class, Payment::class, Refund::class, ReturnRequest::class, ReturnLine::class] as $model) {
            Gate::policy($model, SuperadminOnlyPolicy::class);
        }

        // Catálogo de todas las tiendas: superadministradores y gestores de catálogo.
        foreach ([BaseGarment::class, GarmentSize::class, Product::class, ProductVariant::class, ProductImage::class, Collection::class] as $model) {
            Gate::policy($model, CatalogPolicy::class);
        }

        Gate::policy(StockLevel::class, StockLevelPolicy::class);
        Gate::policy(StockMovement::class, StockMovementPolicy::class);

        // Número de piezas de la cesta en la cabecera de todas las tiendas y de la matriz.
        View::composer(['storefront.market', 'storefront.designs.*.layout', 'checkout.*'], function ($view): void {
            $view->with('cartCount', app(CartStore::class)->count());
        });
    }
}
