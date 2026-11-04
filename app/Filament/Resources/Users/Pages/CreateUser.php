<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Platform\Security\SecurityEventType;
use App\Platform\Security\SecurityLog;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    private ?string $role = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->role = $data['role'] ?? null;
        unset($data['role']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $user = $this->getRecord();
        if ($user instanceof User && $this->role !== null) {
            $user->syncRoles([$this->role]);
            SecurityLog::record(SecurityEventType::RoleChanged, "Rol asignado a {$user->email}: {$this->role}.", subject: 'Usuario #'.$user->id);
        }
    }
}
