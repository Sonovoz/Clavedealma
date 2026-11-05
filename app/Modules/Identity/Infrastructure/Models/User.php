<?php

namespace App\Modules\Identity\Infrastructure\Models;

use App\Modules\Identity\Contracts\Role;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
#[UseFactory(UserFactory::class)]
/**
 * Persona con acceso: equipo del panel o cliente de la tienda («Mi cuenta»).
 * Los clientes confirman su email (MustVerifyEmail) antes de ver los pedidos que hicieron
 * como invitados con ese email.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property ?Carbon $email_verified_at
 * @property-read ?CustomerAddress $address
 */
class User extends Authenticatable implements FilamentUser, MustVerifyEmail
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

    /**
     * @return HasOne<CustomerAddress, $this>
     */
    public function address(): HasOne
    {
        return $this->hasOne(CustomerAddress::class);
    }

    public function isCustomer(): bool
    {
        return $this->hasRole(Role::Customer->value);
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
