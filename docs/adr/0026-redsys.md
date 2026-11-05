# ADR 0026 · Redsys como pasarela de pago

- Estado: aceptado. Sustituye a la parte de Stripe de la ADR 0002.
- Fecha: 2026-11-05

## Contexto

La ADR 0002 eligió Stripe. Pero **Bizum no es un método nativo de Stripe**, y en España el cliente espera pagar con Bizum y con el TPV de su banco. Además, las comisiones del TPV se negocian con el banco y suelen ser más bajas, y el dinero llega directo a la cuenta. La compra ya pasaba por el contrato `PaymentGateway`, así que cambiar de pasarela es añadir un driver.

## Decisión

- **Redsys es la pasarela de Clave de Alma** (`PAYMENT_DRIVER=redsys`, driver `Payments\Infrastructure\Drivers\RedsysGateway`).
  - Usa la integración por **redirección**, la del plugin de Redsys para WooCommerce: el cliente paga en la página segura de Redsys.
  - **Stripe se queda** en el código como driver apagado y **fake** sigue siendo el de local.
- **«Métodos de pago», como en WooCommerce**:
  - En «Finalizar compra» y en «Pagar el pedido», el cliente elige **Tarjeta de crédito o débito** o **Bizum**. Bizum aparece si `REDSYS_BIZUM=true` y el banco lo tiene activado.
  - El contrato gana `methods()`, y el cobro guarda el método elegido (`payments.method`), que se ve en «Pedido recibido».
  - Apple Pay y Google Pay, si el banco los activa, salen solos en la página de Redsys.
- **Firma HMAC_SHA256_V1**, igual que la librería oficial (`Drivers\Redsys\RedsysSignature`): la clave del comercio se diversifica por pedido con 3DES y se firma con HMAC-SHA256.
  - Todo lo que llega de Redsys se comprueba antes de usarlo.
  - Lo que no cuadra se rechaza (400 en el aviso) o se ignora (en la vuelta del cliente).
- **El cobro se confirma** por dos caminos, los dos por `HandleGatewayEvent`, que aplica cada resultado una sola vez:
  - el **aviso firmado** de Redsys a `/webhooks/redsys`;
  - el **resultado firmado** que trae el cliente al volver (`PaymentGateway::returnEvent`). Este vale cuando el aviso no llega, como en local.
- **Un número de pedido de Redsys nuevo por cada visita a su página**, porque Redsys no deja repetirlos. Son 12 cifras, las 4 primeras del pedido.
  - Solo se cobra si el cliente paga allí.
  - Si un pedido ya pagado recibe un segundo cobro distinto (dos pestañas), `MarkOrderPaid` lo deja «a revisar» para devolver uno.
- **Devoluciones** por la API REST de Redsys (operación 3, sobre el mismo número de pedido). Solo cuentan con la respuesta firmada `0900`.
- **Entornos**:
  - `REDSYS_ENVIRONMENT=test` (sis-t.redsys.es) no cobra y marca los pedidos de prueba.
  - `live` (sis.redsys.es) es el real.
  - El contrato gana `testMode()`, que también vale para Stripe con claves `sk_test_`.
- **Textos legales**: privacidad, cookies y condiciones dicen la pasarela activa (variable `gateway` de `LegalGenerator`). Con Redsys no hay cookies de pago en nuestra web.

## Consecuencias

- Los datos del TPV (código de comercio, terminal y clave secreta) solo van en el `.env` del servidor: nunca en el código, en GitHub ni en el chat.
- Al banco hay que darle la URL de notificación y pedirle «parámetros en las URLs». Si quiere el cliente, también Bizum.
- El cliente sale a la página del banco y vuelve. Es lo habitual en las tiendas españolas, pero el pago ya no ocurre dentro de «Finalizar compra», como pasaba con Stripe.
