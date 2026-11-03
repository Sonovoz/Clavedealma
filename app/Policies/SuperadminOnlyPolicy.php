<?php

namespace App\Policies;

use App\Modules\Identity\Infrastructure\Models\User;

/**
 * Todo denegado: solo el superadministrador pasa, gracias a Gate::before
 * (IdentityServiceProvider). Cada acción se declara explícitamente porque
 * Filament considera permitida cualquier acción que la política no defina.
 */
class SuperadminOnlyPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user): bool
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

    public function restore(User $user): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
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
        return false;
    }

    public function replicate(User $user): bool
    {
        return false;
    }
}
