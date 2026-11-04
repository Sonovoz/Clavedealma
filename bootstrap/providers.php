<?php

use App\Modules\Cart\CartServiceProvider;
use App\Modules\Catalog\CatalogServiceProvider;
use App\Modules\Identity\IdentityServiceProvider;
use App\Modules\Inventory\InventoryServiceProvider;
use App\Modules\Orders\OrdersServiceProvider;
use App\Modules\Payments\PaymentsServiceProvider;
use App\Modules\Shipping\ShippingServiceProvider;
use App\Modules\Stores\StoresServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;

return [
    AppServiceProvider::class,
    IdentityServiceProvider::class,
    StoresServiceProvider::class,
    CatalogServiceProvider::class,
    InventoryServiceProvider::class,
    ShippingServiceProvider::class,
    CartServiceProvider::class,
    OrdersServiceProvider::class,
    PaymentsServiceProvider::class,
    AdminPanelProvider::class,
];
