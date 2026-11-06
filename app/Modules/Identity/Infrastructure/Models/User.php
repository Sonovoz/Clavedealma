<?php

namespace App\Modules\Identity\Infrastructure\Models;

use App\Modules\Identity\Contracts\Role;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
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
 * como invitados con ese email. En el panel, verificación en dos pasos con una app
 * autenticadora (ADR 0028): obligatoria para el equipo, opcional para los artistas.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property ?Carbon $email_verified_at
 * @property-read ?CustomerAddress $address
 */
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery, Notifiable;

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

    /** Tiene activada la verificación en dos pasos (app autenticadora). */
    public function hasTwoFactor(): bool
    {
        return filled($this->app_authentication_secret);
    }

    /**
     * Debe usar la verificación en dos pasos para entrar al panel (ADR 0028): todo el equipo
     * (ve clientes, cobros o el TPV). Los artistas solo ven sus ventas: para ellos es opcional.
     */
    public function mustUseTwoFactor(): bool
    {
        return (bool) config('clavedealma.security.two_factor_required')
            && $this->hasAnyRole([Role::Superadmin->value, Role::CatalogManager->value, Role::Manufacturer->value]);
    }

    public function isArtist(): bool
    {
        return $this->hasRole(Role::Artist->value);
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
