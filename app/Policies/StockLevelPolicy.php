<?php

namespace App\Policies;

use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Infrastructure\Models\User;

/**
 * El stock lo gestionan los fabricantes (y el superadministrador vía Gate::before).
 * Las fichas de stock se crean solas al crear una variante: nadie las crea ni borra a mano.
 */
class StockLevelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Manufacturer->value);
    }

    public function view(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
