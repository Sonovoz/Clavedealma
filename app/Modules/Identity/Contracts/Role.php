<?php

namespace App\Modules\Identity\Contracts;

/**
 * Roles del sistema. El valor es el nombre guardado en la tabla `roles`.
 */
enum Role: string
{
    case Superadmin = 'superadmin';
    case Manufacturer = 'fabricante';
    case Artist = 'artista';
    case Customer = 'cliente';

    public function label(): string
    {
        return match ($this) {
            self::Superadmin => 'Superadministrador',
            self::Manufacturer => 'Fabricante',
            self::Artist => 'Artista',
            self::Customer => 'Cliente',
        };
    }

    /**
     * Roles que pueden entrar en el panel de gestión (/admin).
     * Los clientes usan su cuenta en la tienda, nunca el panel.
     */
    public function canAccessAdminPanel(): bool
    {
        return $this !== self::Customer;
    }

    /**
     * @return list<self>
     */
    public static function panelRoles(): array
    {
        return array_values(array_filter(self::cases(), fn (self $role): bool => $role->canAccessAdminPanel()));
    }
}
