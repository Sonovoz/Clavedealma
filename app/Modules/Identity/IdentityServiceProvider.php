<?php

namespace App\Modules\Identity;

use App\Modules\Identity\Infrastructure\Console\CreateSuperadminCommand;
use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class IdentityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([CreateSuperadminCommand::class]);
        }

        // El superadministrador puede hacer cualquier acción autorizada por políticas.
        Gate::before(fn (User $user): ?bool => $user->isSuperadmin() ? true : null);
    }
}
