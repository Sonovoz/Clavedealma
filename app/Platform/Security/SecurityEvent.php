<?php

namespace App\Platform\Security;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Una entrada del registro de seguridad. Solo se añaden: no se modifican ni se borran
 * a mano (la purga por antigüedad la hace seguridad:purgar).
 *
 * @property int $id
 * @property SecurityEventType $type
 * @property string $level
 * @property ?int $user_id
 * @property ?string $user_email
 * @property ?string $ip
 * @property ?string $user_agent
 * @property ?string $subject
 * @property string $description
 * @property ?array<string, mixed> $context
 * @property Carbon $created_at
 */
class SecurityEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['type' => SecurityEventType::class, 'context' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('El registro de seguridad no se modifica.'));
        static::deleting(fn (): never => throw new LogicException('El registro de seguridad no se borra a mano.'));
    }
}
