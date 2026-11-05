# Compra y pago

Clave de Alma es el **único vendedor**. Por eso hay un solo carrito para todas las tiendas, un solo pago y un solo pedido, aunque lleve piezas de varios artistas.

El recorrido copia el de WooCommerce, para que resulte familiar: los mismos pasos, nombres y pantallas, con el aspecto de Clave de Alma.

## Recorrido del cliente

| Paso | Dirección | Qué pasa |
| --- | --- | --- |
| Ficha | `clavedealma.es/<tienda>/p/<artículo>` | Elige color, talla y cantidad y pulsa **Añadir al carrito**. El botón solo se activa con una variante disponible. |
| Carrito | `/carrito` | Tabla con ×, precio, cantidad y subtotal, **Actualizar carrito** y «Totales del carrito» con **Calcular envío**. Si algo se ha agotado o ya no está a la venta, se avisa y no deja seguir. |
| Finalizar compra | `/finalizar-compra` | Una sola página: *Detalles de facturación*, *Notas del pedido*, «Tu pedido», el pago y **Realizar el pedido**. El envío se calcula al escribir el código postal. Hay que aceptar las condiciones. |
| Pagar el pedido | `/pedido/<código>/pagar` | Solo si hace falta: sin JavaScript, si Stripe no carga o desde «Volver al pago». Es la página «Pagar el pedido» de WooCommerce. |
| Pedido recibido | `/pedido/<código>` | «Gracias. Tu pedido ha sido recibido.» con número, fecha, email, total y método de pago, más la dirección de envío. Si el pago aún no está confirmado, espera y se recarga sola. Después es la página del pedido del cliente (seguimiento, facturas y devoluciones). |

`/pedido` redirige a `/finalizar-compra`.

### Cómo funciona «Realizar el pedido»

1. **Con Stripe**, todo ocurre en la misma página:
   1. El Payment Element de Stripe comprueba los datos de pago (`elements.submit()`).
   2. El navegador envía el formulario en JSON. El servidor valida los datos, calcula el total, crea el pedido y su cobro, y devuelve el `client_secret`.
   3. Stripe confirma el pago (`confirmPayment`) y lleva a «Pedido recibido».

   El importe que se cobra lo fija siempre el servidor. El que se pasa al Payment Element solo sirve para que Apple Pay o Google Pay enseñen la cifra.
2. **Con el pago simulado**, la caja de pago tiene «Simular pago correcto» y «Simular pago rechazado». Si se rechaza, vuelve a «Finalizar compra» con el aviso.
3. **Si el cliente pulsa otra vez** (tarjeta rechazada, error) con el mismo carrito y los mismos datos, se reutiliza el pedido que espera el pago. Si ha cambiado algo, el anterior se anula, y su cobro con él (`Orders\Application\CancelPendingOrder`), para que nunca haya dos pedidos pagables.

Ninguna página de compra se indexa (`noindex` y `robots.txt`).

## Mi cuenta (opcional)

Como en WooCommerce, se puede comprar como invitado o con cuenta (ADR 0021).

| Página | Dirección | Qué hay |
| --- | --- | --- |
| Acceder / Registrarse | `/mi-cuenta` | Email y contraseña, «Recuérdame» y «¿Has olvidado tu contraseña?» (`/mi-cuenta/recuperar-contrasena`). |
| Escritorio | `/mi-cuenta` | «Hola Ana (¿no eres Ana? Cerrar sesión)» y los pedidos recientes. |
| Pedidos | `/mi-cuenta/pedidos` | Pedido · Fecha · Estado (Procesando, Completado…) · Total («39,95 € para 1 artículo») · Ver / Pagar. |
| Ver pedido | `/mi-cuenta/ver-pedido/<pedido>` | «El pedido … se realizó el … y está actualmente …», seguimiento, facturas, devoluciones y detalles. |
| Facturas | `/mi-cuenta/facturas` | Todas sus facturas en PDF. |
| Devoluciones | `/mi-cuenta/devoluciones` | Sus devoluciones y su estado. |
| Direcciones | `/mi-cuenta/direcciones` | La dirección de facturación y envío que rellena «Finalizar compra». |
| Detalles de la cuenta | `/mi-cuenta/editar-cuenta` | Nombre, email y cambio de contraseña. |

- En «Finalizar compra», el invitado ve **«¿Ya eres cliente? Haz clic aquí para acceder»** (vuelve a la compra al acceder) y **«¿Crear una cuenta?»**, con la contraseña.
- Con sesión, los datos salen rellenos y la dirección del pedido se guarda en la cuenta.
- Los pedidos hechos como invitado con el mismo email aparecen al **confirmar el email**.
- En el panel: *Comercio → Clientes* (pedidos, total gastado y último pedido de cada uno). Cada pedido indica «Cuenta: Ana Pérez» o «Invitado».

## Reglas

- **Precios, envío e IVA se calculan siempre en el servidor.** La cesta solo guarda «variante y unidades» y se recalcula al abrirla, al pagar y al crear el pedido.
- **El envío** lo cobra el baremo por peso del destino (*Ajustes → Zonas de envío*):
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

Es el valor por defecto en local. «Finalizar compra» (y «Pagar el pedido») muestran **Simular pago correcto** y **Simular pago rechazado**. El resto funciona igual que con un pago real: pedido, stock, emails y panel.

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

En local (Herd) el webhook de Stripe no puede llegar a tu ordenador. No pasa nada: al volver de Stripe, «Pedido recibido» pregunta a Stripe el estado del cobro y aplica el mismo proceso. Si quieres probar también el webhook, usa la CLI de Stripe: `stripe listen --forward-to clavedealma.test/webhooks/stripe`.

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

## Después del pago: fabricación y envío

```text
Pagado · por fabricar → En fabricación → Listo para enviar → Enviado → Entregado
```

| Paso | Quién | Dónde |
| --- | --- | --- |
| Empezar fabricación | Fabricante (o superadmin) | *Comercio → Fabricación* |
| Listo para enviar | Fabricante (o superadmin) | *Comercio → Fabricación* |
| Marcar enviado | Superadmin | *Comercio → Pedidos → el pedido*: transportista + número de seguimiento. El cliente recibe un email con el enlace. |
| Marcar entregado | Superadmin | *Comercio → Pedidos → el pedido* |

- **Fabricación** enseña solo lo que hay que fabricar: piezas, SKU, talla, color, si es personalizada y la fecha en que debe estar lista, en rojo si se pasa. **No enseña nombre, email ni dirección del cliente.**
- **El enlace de seguimiento** sale de la plantilla del transportista en *Ajustes → Transportistas*, por ejemplo `https://…?numero={codigo}`. También se puede pegar a mano al enviar.
- **El cliente** ve en la página de su pedido (el enlace «Ver mi pedido» de los emails) en qué paso está y el seguimiento.
- **Cancelar y devolver el importe** (*Comercio → Pedidos → el pedido*):
  - Se puede hasta que el pedido sale.
  - Devuelve todo lo cobrado al medio de pago del cliente.
  - Repone en el stock las piezas que salieron de él.
  - Envía un email al cliente con el motivo.
  - Si la pasarela no hace la devolución, el pedido no se cancela y el fallo queda en el historial.

## Devoluciones

El cliente tiene **14 días naturales desde la entrega** para devolver las piezas que no sean personalizadas. Si no se marcó la entrega, el plazo se cuenta desde el envío más el plazo de entrega.

1. **El cliente lo pide** desde la página de su pedido (botón **Devolver piezas**, en el enlace «Ver mi pedido» de los emails). Elige qué piezas y cuántas, el motivo y, si quiere, un comentario. Recibe un email y el equipo recibe un aviso.
2. **El equipo revisa** en *Comercio → Devoluciones → Por revisar* y decide:
   - **Aprobar**: se envían al cliente las instrucciones, que vienen ya escritas con la dirección de `RETURNS_ADDRESS` y se pueden cambiar.
   - **Rechazar**: con el motivo, que se le envía por email.
3. **Al recibir el paquete**, pulsa **Recibida: devolver X €**. Si las piezas están bien, deja marcado «reponerlas en el stock». El dinero vuelve al medio de pago del cliente y se le avisa.

Importe:
- Se devuelven las piezas y, **si vuelve el pedido entero, también el envío pagado**.
- El envío de vuelta lo paga el cliente, salvo que la pieza llegara con un defecto o no fuera la pedida. En ese caso el panel avisa de que lo paga Clave de Alma.
- Si la pasarela falla, la devolución queda en **Dinero pendiente** con el error y hay un botón para reintentar.

Configura en el `.env` la dirección a la que se envían las devoluciones:

```text
RETURNS_ADDRESS="Clave de Alma · Devoluciones, Calle …, CP Ciudad"
```

## Panel: Comercio → Pedidos

Solo lo ve el superadministrador. El gestor de catálogo no ve datos de clientes ni de cobros.

- **Listado**: referencia (`CDA-26-000123`), cliente, destino, total y estado. Tiene filtros por estado, «a revisar» y «compras de prueba». El contador rojo del menú indica los pedidos a revisar.
- **Detalle**: piezas, importes con IVA, cobro, plazo, dirección e historial.
- El pedido no tiene botón de editar ni de borrar.
- Tiene pestañas: Por fabricar, En fabricación, Para enviar, Enviados y A revisar.
- La ficha enseña las devoluciones del pedido, con enlace a cada una.

## Antes de vender de verdad

- [ ] Textos legales revisados por la asesoría. Se generan solos al guardar los datos fiscales (ADR 0020) y se marcan como revisados en *Ajustes → Textos legales*. Cada pedido guarda la versión de las condiciones que aceptó.
- [ ] Datos fiscales de Clave de Alma en el pie y en los emails. Las facturas llegan en la fase 6.
- [ ] Decisión fiscal sobre Canarias, Ceuta y Melilla, y sobre el umbral de la UE (OSS).
- [ ] Tarifas de envío reales en el panel.
- [ ] Claves de Stripe en modo real (`pk_live_` / `sk_live_`) y su webhook, **solo en producción**.
- [ ] Correo de salida (SMTP) configurado y probado.
- [ ] Dirección de devoluciones (`RETURNS_ADDRESS`).
