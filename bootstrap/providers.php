<?php

use App\Modules\Identity\IdentityServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;

return [
    AppServiceProvider::class,
    IdentityServiceProvider::class,
    AdminPanelProvider::class,
];
