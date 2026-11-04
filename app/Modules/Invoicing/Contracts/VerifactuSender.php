<?php

namespace App\Modules\Invoicing\Contracts;

/**
 * Envío de los registros de facturación a la AEAT (Veri*Factu). Drivers: «none» (se
 * registran en local y quedan sin enviar) y, con el certificado de Clave de Alma, «aeat».
 */
interface VerifactuSender
{
    public function configured(): bool;

    /**
     * @param  array<string, mixed>  $record  datos del registro (emisor, número, fecha, tipo, importes, huellas)
     * @return array{status: AeatStatus, error: ?string}
     */
    public function send(array $record): array;
}
