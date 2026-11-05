<?php

namespace App\Modules\Stores\Contracts;

use App\Support\Contracts\HasLabel;

/** Estado de un aviso de artista (ADR 0023). */
enum NoticeStatus: string implements HasLabel
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Nuevo',
            self::InProgress => 'En curso',
            self::Done => 'Hecho',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::InProgress => 'info',
            self::Done => 'success',
        };
    }
}
