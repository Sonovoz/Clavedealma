<?php

namespace App\Modules\Invoicing\Infrastructure\Verifactu;

use App\Modules\Invoicing\Contracts\AeatStatus;
use App\Modules\Invoicing\Contracts\VerifactuSender;

/**
 * Sin envío a la AEAT: el registro (huella y QR) se guarda igualmente y queda «sin enviar».
 * Se sustituye por el driver «aeat» cuando esté el certificado digital de Clave de Alma.
 */
class NullVerifactuSender implements VerifactuSender
{
    public function configured(): bool
    {
        return false;
    }

    public function send(array $record): array
    {
        return ['status' => AeatStatus::NotConfigured, 'error' => null];
    }
}
