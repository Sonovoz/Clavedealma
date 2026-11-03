<?php

use App\Modules\Catalog\CatalogServiceProvider;
use App\Modules\Identity\IdentityServiceProvider;
use App\Modules\Inventory\InventoryServiceProvider;
use App\Modules\Stores\StoresServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;

return [
    AppServiceProvider::class,
    IdentityServiceProvider::class,
    StoresServiceProvider::class,
    CatalogServiceProvider::class,
    InventoryServiceProvider::class,
    AdminPanelProvider::class,
];
