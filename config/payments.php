<?php

/*
| Pagos (ADR 0002 y 0014, docs/compra/README.md).
| - "fake": pago simulado para probar la compra sin claves. Prohibido en producción.
| - "stripe": Stripe en modo test (claves pk_test_/sk_test_) o real (pk_live_/sk_live_).
*/
return [
    'driver' => env('PAYMENT_DRIVER', 'fake'),

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],
];
