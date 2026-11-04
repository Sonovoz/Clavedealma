<?php

/*
| Facturación (ADR 0018, docs/facturacion.md). Los datos fiscales y las series se
| rellenan en el panel: Administración → Datos de facturación.
*/
return [
    'verifactu' => [
        // none = los registros (huella y QR) se guardan en local sin enviar; aeat = envío
        // con el certificado digital de Clave de Alma (pendiente de integrar).
        'driver' => env('VERIFACTU_DRIVER', 'none'),
        // false = el QR apunta al entorno de pruebas de la AEAT.
        'production' => (bool) env('VERIFACTU_PRODUCTION', false),
    ],
];
