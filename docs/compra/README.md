# Compra y pago

Clave de Alma es el **único vendedor**. Por eso hay una sola cesta para todas las tiendas, un solo pago y un solo pedido, aunque lleve piezas de varios artistas.

## Recorrido del cliente

| Paso | Dirección | Qué pasa |
| --- | --- | --- |
| Ficha | `clavedealma.es/<tienda>/p/<artículo>` | Elige color y talla y pulsa **Añadir a la cesta**. El botón solo se activa con una variante disponible. |
| Cesta | `/carrito` | Cambia unidades o quita piezas. Si algo se ha agotado o ya no está a la venta, se avisa y no deja seguir. |
| Datos y envío | `/pedido` | Contacto y dirección. El precio del envío se calcula al escribir el código postal. Hay que aceptar las condiciones. |
| Pago | `/pedido/<código>/pagar` | Stripe (tarjeta, Apple Pay, Google Pay…) o el pago simulado en pruebas. |
| Confirmación | `/pedido/<código>` | «Gracias» + email de confirmación. Si el pago aún no está confirmado, la página espera y se recarga sola. |

Ninguna página de compra se indexa (`noindex` y `robots.txt`).

## Reglas

- **Precios, envío e IVA se calculan siempre en el servidor.** La cesta solo guarda «variante y unidades» y se recalcula al abrirla, al pagar y al crear el pedido.
- **El envío** lo cobra el baremo por peso del destino (*Envíos → Tarifas por destino*):
  - Se usa el peso de la prenda base. Si no lo tiene, 400 g por pieza (`SHOP_DEFAULT_WEIGHT_GRAMS`).
  - Si el destino no tiene tarifa activa, el cliente lee «Todavía no enviamos a…».
- **Canarias, Ceuta y Melilla están cerradas** aunque tengan tarifa, porque llevan otros impuestos (IGIC, IPSI) y aduana. Se abrirán cuando la asesoría fiscal lo resuelva (`clavedealma.shop.blocked_zones`).
- **Portugal y el resto de la UE** se venden con IVA español mientras las ventas a distancia en la UE no pasen de 10.000 € al año. A partir de ahí hay que darse de alta en la ventanilla única (OSS). **Lo confirma la asesoría fiscal.**
- **El stock se descuenta al confirmarse el pago**, no al añadir a la cesta:
  - *Bajo pedido*: no hay límite.
  - *Stock y después bajo pedido*: sale lo que haya y el resto se fabrica.
  - *Unidades limitadas*: si dos clientes pagan la última unidad a la vez, el segundo pedido queda **«a revisar»**. Hay que fabricar la pieza o devolver el importe.
- **El pedido es inmutable**: los datos, las líneas y los importes no cambian nunca. Cada línea guarda lo vendido: nombre, talla, color, precio, IVA, comisión del artista (la del artículo o, si no tiene, la de la tienda) y peso. Solo avanza el estado, y cada cambio queda en el historial.
- **Pedidos sin pagar**: se cancelan solos a las 2 horas (`pedidos:cancelar-caducados`, cada 15 minutos) y se anula su cobro pendiente.
- **Compras de prueba**: las hechas con el pago simulado, o por el equipo en una tienda sin publicar, se marcan «Prueba».
- **El equipo** (superadmin y gestor de catálogo) puede comprar en una tienda oculta para probarla. El público, no.

## Pagos

Todo va por el contrato `Payments\Contracts\PaymentGateway` (ADR 0002 y 0014). Se elige con `PAYMENT_DRIVER`.

### Sin claves: pago simulado (`PAYMENT_DRIVER=fake`)

Es el valor por defecto en local. La página de pago muestra **Simular pago correcto** y **Simular pago rechazado**. El resto funciona igual que con un pago real: pedido, stock, emails y panel.

**Nunca funciona en producción**: la aplicación se niega a arrancar el pago.

### Stripe en modo test (`PAYMENT_DRIVER=stripe`)

1. En [dashboard.stripe.com](https://dashboard.stripe.com), con el **modo de prueba** activado, entra en *Desarrolladores → Claves de API* y copia la clave publicable (`pk_test_…`) y la secreta (`sk_test_…`).
2. En *Desarrolladores → Webhooks*, añade el endpoint `https://<tu-dominio>/webhooks/stripe` con los eventos `payment_intent.succeeded` y `payment_intent.payment_failed`. Copia el secreto de firma (`whsec_…`).
3. En el `.env` del servidor (**nunca en GitHub ni en el chat**):

   ```text
   PAYMENT_DRIVER=stripe
   STRIPE_KEY=pk_test_…
   STRIPE_SECRET=sk_test_…
   STRIPE_WEBHOOK_SECRET=whsec_…
   ```

4. Ejecuta `php artisan config:clear`.
5. Paga con las tarjetas de prueba de Stripe, por ejemplo `4242 4242 4242 4242`, con cualquier fecha futura y cualquier CVC. La tarjeta `4000 0000 0000 0002` se rechaza.

En local (Herd) el webhook de Stripe no puede llegar a tu ordenador. No pasa nada: al volver de Stripe, la página de confirmación pregunta a Stripe el estado del cobro y aplica el mismo proceso. Si quieres probar también el webhook, usa la CLI de Stripe: `stripe listen --forward-to clavedealma.test/webhooks/stripe`.

**Métodos de pago**: el formulario de Stripe enseña los métodos activados en *Configuración → Métodos de pago* de vuestra cuenta (tarjeta, Apple Pay, Google Pay…). **Bizum no es un método nativo de Stripe**. Si es imprescindible, se añadirá otro driver (Redsys o MONEI) sin tocar la compra (ADR 0002).

**Seguridad**:
- Los datos de la tarjeta los recoge Stripe en su propio formulario, así que nunca pasan por nuestro servidor (PCI SAQ A).
- El pago solo se da por bueno cuando lo dice Stripe, por el webhook firmado o por la consulta al volver.
- Cada aviso se aplica una sola vez.
- Recargar la página de pago reutiliza el mismo cobro: nunca se cobra dos veces.
- Si el importe cobrado no coincide con el del pedido, el pedido queda pagado pero «a revisar».

## Emails

Al confirmarse el pago se envían dos correos:
- **al cliente**, la confirmación con las piezas, los importes, el plazo y la dirección;
- **al equipo**, un aviso a `ORDERS_ALERT_EMAIL` (o, si está vacío, a `SUPERADMIN_EMAIL`).

En local (`MAIL_MAILER=log`) los correos quedan en `storage/logs/laravel.log`. Si un correo falla, el pago no se pierde: queda anotado en el historial del pedido.

## Panel: Ventas → Pedidos

Solo lo ve el superadministrador. El gestor de catálogo no ve datos de clientes ni de cobros.

- **Listado**: referencia (`CDA-26-000123`), cliente, destino, total y estado. Tiene filtros por estado, «a revisar» y «compras de prueba». El contador rojo del menú indica los pedidos a revisar.
- **Detalle**: piezas, importes con IVA, cobro, plazo, dirección e historial.
- El pedido no tiene botón de editar ni de borrar.
- La fabricación, el envío y las devoluciones llegan en la fase 5.

## Antes de vender de verdad

- [ ] Textos legales definitivos (condiciones de venta, privacidad, envíos y devoluciones) de la asesoría legal. Hoy `/legal/…` dice que están pendientes. Al publicarlos, cambiar `SHOP_TERMS_VERSION`.
- [ ] Datos fiscales de Clave de Alma en el pie y en los emails. Las facturas llegan en la fase 6.
- [ ] Decisión fiscal sobre Canarias, Ceuta y Melilla, y sobre el umbral de la UE (OSS).
- [ ] Tarifas de envío reales en el panel.
- [ ] Claves de Stripe en modo real (`pk_live_` / `sk_live_`) y su webhook, **solo en producción**.
- [ ] Correo de salida (SMTP) configurado y probado.
