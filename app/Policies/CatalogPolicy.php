<?php

namespace App\Policies;

use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Infrastructure\Models\User;

/**
 * Catálogo (productos, variantes, fotos, colecciones, prendas base): lo gestiona
 * el equipo interno de catálogo en todas las tiendas, además del superadministrador
 * (Gate::before). Borrar para siempre queda reservado al superadministrador.
 */
class CatalogPolicy
{
    private function manages(User $user): bool
    {
        return $user->hasRole(Role::CatalogManager->value);
    }

    public function viewAny(User $user): bool
    {
        return $this->manages($user);
    }

    public function view(User $user): bool
    {
        return $this->manages($user);
    }

    public function create(User $user): bool
    {
        return $this->manages($user);
    }

    public function update(User $user): bool
    {
        return $this->manages($user);
    }

    public function delete(User $user): bool
    {
        return $this->manages($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->manages($user);
    }

    public function restore(User $user): bool
    {
        return $this->manages($user);
    }

    public function restoreAny(User $user): bool
    {
        return $this->manages($user);
    }

    public function forceDelete(User $user): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return $this->manages($user);
    }

    public function replicate(User $user): bool
    {
        return $this->manages($user);
    }
}
