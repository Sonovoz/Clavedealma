<?php

/*
| Pagos (ADR 0002 y 0014, docs/compra/README.md).
| - "fake": pago simulado para probar la compra sin claves. Prohibido en producción.
| - "redsys": TPV virtual del banco (ADR 0026), la pasarela de Clave de Alma. Tarjeta y,
|   si el banco lo tiene activado en el TPV, Bizum. Entorno "test" o "live".
| - "stripe": Stripe en modo test (claves pk_test_/sk_test_) o real (pk_live_/sk_live_).
| Las claves solo en el .env del servidor: nunca en el código ni en GitHub.
*/
return [
    'driver' => env('PAYMENT_DRIVER', 'fake'),

    'redsys' => [
        'merchant_code' => env('REDSYS_MERCHANT_CODE', ''),
        'terminal' => env('REDSYS_TERMINAL', '1'),
        'secret_key' => env('REDSYS_SECRET_KEY', ''),
        'environment' => env('REDSYS_ENVIRONMENT', 'test'),
        'bizum' => (bool) env('REDSYS_BIZUM', false),
        'merchant_name' => env('REDSYS_MERCHANT_NAME', 'Clave de Alma'),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],
];
