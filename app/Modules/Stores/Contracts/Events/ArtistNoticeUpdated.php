<?php

namespace App\Modules\Stores\Contracts\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** El equipo ha cambiado el estado de un aviso (o ha respondido). */
class ArtistNoticeUpdated
{
    use Dispatchable;

    public function __construct(public readonly int $noticeId) {}
}
