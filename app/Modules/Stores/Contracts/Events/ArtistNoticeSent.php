<?php

namespace App\Modules\Stores\Contracts\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** Un artista ha enviado un aviso a Clave de Alma. */
class ArtistNoticeSent
{
    use Dispatchable;

    public function __construct(public readonly int $noticeId) {}
}
