<?php

namespace App\Modules\Shipping\Contracts;

interface CarrierDirectory
{
    /**
     * Transportistas activos, por nombre.
     *
     * @return list<string>
     */
    public function names(): array;

    /** Enlace de seguimiento del envío según la plantilla del transportista (null si no tiene). */
    public function trackingUrl(string $carrier, string $trackingNumber): ?string;
}
