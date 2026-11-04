<?php

namespace App\Modules\Identity\Infrastructure\Models;

use App\Modules\Identity\Contracts\Role;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
#[UseFactory(UserFactory::class)]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRole(array_map(fn (Role $role): string => $role->value, Role::panelRoles()));
    }

    public function isSuperadmin(): bool
    {
        return $this->hasRole(Role::Superadmin->value);
    }

    /** Superadministrador o gestor de catálogo: altas y cambios de artículos. */
    public function managesCatalog(): bool
    {
        return $this->hasAnyRole([Role::Superadmin->value, Role::CatalogManager->value]);
    }
}
