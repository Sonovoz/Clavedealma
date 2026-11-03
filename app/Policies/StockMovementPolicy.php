<?php

namespace App\Policies;

use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Infrastructure\Models\User;

/**
 * Historial de stock: lo consultan los fabricantes. Nadie lo modifica.
 */
class StockMovementPolicy extends SuperadminOnlyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Manufacturer->value);
    }

    public function view(User $user): bool
    {
        return $this->viewAny($user);
    }
}
