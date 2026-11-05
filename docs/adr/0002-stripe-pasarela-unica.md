# ADR 0002 · Stripe como pasarela única

- **Estado:** sustituida por la [ADR 0026](0026-redsys.md): la pasarela es Redsys. Stripe se queda como driver apagado.
- **Fecha:** 2026-11-03

## Decisión

Stripe, con una sola cuenta de Clave de Alma: tarjeta, Bizum, Apple Pay y Google Pay. Se integra a través del contrato propio `PaymentGateway`, con el driver `StripeGateway`.

## Reglas

- Los datos de tarjeta nunca pasan por nuestro servidor (Payment Element / Checkout): PCI SAQ A.
- El webhook de Stripe es la fuente de verdad del pago.
- Cada cobro lleva clave de idempotencia; cada webhook se registra y procesa una sola vez.
- SDK oficial `stripe/stripe-php`; no se usa Laravel Cashier (pensado para suscripciones).

## Alternativas

MONEI o Redsys podrían añadirse como otro driver sin tocar el checkout.
