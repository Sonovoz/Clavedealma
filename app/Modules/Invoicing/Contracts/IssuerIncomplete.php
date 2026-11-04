<?php

namespace App\Modules\Invoicing\Contracts;

use RuntimeException;

/** Faltan datos fiscales de Clave de Alma: la factura queda pendiente (facturas:emitir-pendientes). */
final class IssuerIncomplete extends RuntimeException {}
