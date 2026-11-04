<?php

namespace App\Modules\Payments\Contracts;

use RuntimeException;

/** La pasarela no responde o no está configurada: el cliente puede reintentar. */
final class PaymentUnavailable extends RuntimeException {}
