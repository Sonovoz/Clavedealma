<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Platform\Security\SecurityEventType;
use App\Platform\Security\SecurityLog;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    private ?string $role = null;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $user = $this->getRecord();
        $data['role'] = $user instanceof User ? $user->getRoleNames()->first() : null;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->role = $data['role'] ?? null;
        unset($data['role']);

        return $data;
    }

    protected function afterSave(): void
    {
        $user = $this->getRecord();
        if ($user instanceof User && $this->role !== null) {
            $before = $user->getRoleNames()->first();
            $user->syncRoles([$this->role]);
            if ($before !== $this->role) {
                SecurityLog::record(SecurityEventType::RoleChanged, "Rol de {$user->email}: ".($before ?? 'ninguno')." → {$this->role}.", subject: 'Usuario #'.$user->id);
            }
        }
    }
}
