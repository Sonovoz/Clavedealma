# Compra y pago

Clave de Alma es el **único vendedor**. Por eso hay un solo carrito para todas las tiendas, un solo pago y un solo pedido, aunque lleve piezas de varios artistas.

El recorrido copia el de WooCommerce, para que resulte familiar: los mismos pasos, nombres y pantallas, con el aspecto de Clave de Alma.

## Recorrido del cliente

| Paso | Dirección | Qué pasa |
| --- | --- | --- |
| Ficha | `clavedealma.es/<tienda>/p/<artículo>` | Elige color, talla y cantidad y pulsa **Añadir al carrito**. El botón solo se activa con una variante disponible. |
| Carrito | `/carrito` | Tabla con ×, precio, cantidad y subtotal, **Actualizar carrito** y «Totales del carrito» con **Calcular envío**. Si algo se ha agotado o ya no está a la venta, se avisa y no deja seguir. |
| Finalizar compra | `/finalizar-compra` | Una sola página: *Detalles de facturación*, *Notas del pedido*, «Tu pedido», el pago y **Realizar el pedido**. El envío se calcula al escribir el código postal. Hay que aceptar las condiciones. |
| Pago seguro | (tras «Realizar el pedido») | «Te llevamos a la página de pago segura de Redsys»: el navegador envía el cobro firmado a Redsys y el cliente paga allí con tarjeta o Bizum. |
| Pagar el pedido | `/pedido/<código>/pagar` | Pedido ya creado que espera el pago: al volver de Redsys sin pagar (con el motivo) o desde «Elegir otro método de pago». Es la página «Pagar el pedido» de WooCommerce. |
| Pedido recibido | `/pedido/<código>` | «Gracias. Tu pedido ha sido recibido.» con número, fecha, email, total y método de pago, más la dirección de envío. Si el pago aún no está confirmado, espera y se recarga sola. Después es la página del pedido del cliente (seguimiento, facturas y devoluciones). |

`/pedido` redirige a `/finalizar-compra`.

### Cómo funciona «Realizar el pedido»

1. **Con Redsys** (la pasarela de Clave de Alma, ADR 0026), como el plugin de Redsys para WooCommerce:
   1. En «Métodos de pago» el cliente elige **Tarjeta de crédito o débito** o **Bizum** (si el banco lo tiene activado en el TPV).
   2. Al pulsar «Realizar el pedido», el servidor valida los datos, calcula el total y crea el pedido.
   3. Después crea el cobro firmado y lo envía, en la página «Pago seguro», a la página de pago de Redsys.
   4. El cliente paga en Redsys y vuelve a «Pedido recibido», o a «Pagar el pedido» si no paga.
2. **Con Stripe** (apagado; se queda por si se vuelve a usar), todo ocurre en la misma página:
   1. El Payment Element de Stripe comprueba los datos de pago (`elements.submit()`).
   2. El navegador envía el formulario en JSON. El servidor valida los datos, calcula el total, crea el pedido y su cobro, y devuelve el `client_secret`.
   3. Stripe confirma el pago (`confirmPayment`) y lleva a «Pedido recibido».

   El importe que se cobra lo fija siempre el servidor. El que se pasa al Payment Element solo sirve para que Apple Pay o Google Pay enseñen la cifra.
3. **Con el pago simulado**, la caja de pago tiene «Simular pago correcto» y «Simular pago rechazado». Si se rechaza, vuelve a «Finalizar compra» con el aviso.
4. **Si el cliente pulsa otra vez** (tarjeta rechazada, error) con el mismo carrito y los mismos datos, se reutiliza el pedido que espera el pago. Si ha cambiado algo, el anterior se anula, y su cobro con él (`Orders\Application\CancelPendingOrder`), para que nunca haya dos pedidos pagables.

Ninguna página de compra se indexa (`noindex` y `robots.txt`).

## Cupones

Como en WooCommerce (ADR 0024). Se crean en *Marketing → Cupones*:
- **General**: porcentaje o importe fijo, envío gratis, fechas.
- **Restricción de uso**: gasto mínimo, tienda, excluir rebajas.
- **Límites de uso**: total y por cliente.

En la tienda:
- **Carrito**: «Código de cupón» + **Aplicar cupón**, y la fila «Cupón: CÓDIGO −X € [Quitar]» en «Totales del carrito».
- **Finalizar compra**: «¿Tienes un cupón? Haz clic aquí para introducir tu código».

Cómo se calcula:
- El descuento se reparte entre las líneas del pedido. El IVA, la factura, la comisión del artista y las devoluciones salen de lo que de verdad se cobra.
- Los usos cuentan cuando se paga y se liberan si el pedido se cancela.

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
- **Inventario por producto** (pestaña «Inventario» de la ficha, ADR 0022):
  - *Hay existencias* (lo normal): se fabrica bajo pedido, sin límite.
  - *Agotado*: no se vende por ahora. La ficha se ve con el aviso y no se puede añadir al carrito.
  - *¿Gestionar inventario?* con una cantidad: se vende hasta esa cantidad entre todas las tallas y colores. Se descuenta **al confirmarse el pago**, no al añadir al carrito. Si dos clientes pagan las últimas unidades a la vez, el segundo pedido queda **«a revisar»**: hay que fabricar la pieza o devolver el importe.
- **El pedido es inmutable**: los datos, las líneas y los importes no cambian nunca. Cada línea guarda lo vendido: nombre, talla, color, precio, IVA, comisión del artista (la del artículo o, si no tiene, la de la tienda) y peso. Solo avanza el estado, y cada cambio queda en el historial.
- **Pedidos sin pagar**: se cancelan solos a las 2 horas (`pedidos:cancelar-caducados`, cada 15 minutos) y se anula su cobro pendiente.
- **Compras de prueba**: las hechas con el pago simulado, o por el equipo en una tienda sin publicar, se marcan «Prueba».
- **El equipo** (superadmin y gestor de catálogo) puede comprar en una tienda oculta para probarla. El público, no.

## Pagos

Todo va por el contrato `Payments\Contracts\PaymentGateway` (ADR 0002 y 0014). Se elige con `PAYMENT_DRIVER`.

### Sin claves: pago simulado (`PAYMENT_DRIVER=fake`)

Es el valor por defecto en local. «Finalizar compra» (y «Pagar el pedido») muestran **Simular pago correcto** y **Simular pago rechazado**. El resto funciona igual que con un pago real: pedido, stock, emails y panel.

**Nunca funciona en producción**: la aplicación se niega a arrancar el pago.

### Redsys (`PAYMENT_DRIVER=redsys`), la pasarela de Clave de Alma

Redsys es el TPV virtual del banco. Se contrata con el banco, que da estos datos: **código de comercio (FUC)**, **terminal** y **clave secreta SHA-256**. Al banco hay que darle la **URL de notificación**, `https://<dominio>/webhooks/redsys`, y pedirle tres cosas:
- Bizum, si se quiere.
- «Parámetros en las URLs: Sí», para que el cliente traiga el resultado firmado al volver.
- Apple Pay y Google Pay, si el banco los ofrece. Salen solos en la página de Redsys.

1. **En el panel, *Ajustes → Pagos*** (ADR 0027), como en WooCommerce. Ahí están el estado del TPV, la URL de notificación para el banco y los **pasos para ponerlo en marcha**, que se marcan solos. Con «Editar datos del TPV» se escriben el código de comercio, el terminal y la clave secreta. La clave se guarda cifrada y cada cambio pide tu contraseña.
2. El servidor solo necesita `PAYMENT_DRIVER=redsys` en el `.env`. Los datos del TPV también pueden ir ahí (`REDSYS_MERCHANT_CODE`, `REDSYS_TERMINAL`, `REDSYS_SECRET_KEY`, `REDSYS_ENVIRONMENT`, `REDSYS_BIZUM`; **nunca en GitHub ni en el chat**). Lo del panel manda.
3. **Pruebas** (`REDSYS_ENVIRONMENT=test`): no se cobra nada y los pedidos se marcan de prueba. Redsys publica un comercio de pruebas para cualquiera (compruébalo en su documentación): código `999008881`, terminal `1` y clave `sq7HjrUOBfKmC576ILgskD5srU870gJ7`. La tarjeta de prueba es `4548 8100 0000 0003`, caducidad `12/49`, CVV `123`.
4. **Real** (`REDSYS_ENVIRONMENT=live`): solo cuando se venda de verdad, con los datos reales del banco.

**Cómo se confirma el cobro**:
- Por el **aviso firmado** que Redsys manda a `/webhooks/redsys` (la fuente de verdad).
- Por el **resultado firmado** que trae el cliente al volver (`returnEvent`). Vale aunque el aviso no llegue, como en local (Herd): el aviso de Redsys no puede llegar a tu ordenador.
- Los dos pasan por `HandleGatewayEvent`: se aplica una sola vez. Uno con la firma mal se ignora.

**Devoluciones**: las cancelaciones y devoluciones del panel devuelven el dinero solas por la API REST de Redsys (operación 3, sobre el mismo número de pedido). Si Redsys la rechaza, no se da por hecha y el panel lo dice.

**Números de pedido de Redsys**: Redsys no deja repetir uno, así que cada visita a su página lleva un número nuevo de 12 cifras (las 4 primeras, del pedido). Solo se cobra si el cliente paga allí. Si un mismo pedido se pagase dos veces (dos pestañas), queda «a revisar» para devolver uno desde el panel del TPV.

### Stripe en modo test (`PAYMENT_DRIVER=stripe`, apagado)

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

**Métodos de pago**: el formulario de Stripe enseña los métodos activados en *Configuración → Métodos de pago* de vuestra cuenta (tarjeta, Apple Pay, Google Pay…). **Bizum no es un método nativo de Stripe**: por eso la pasarela es Redsys (ADR 0026).

**Seguridad**:
- Los datos de la tarjeta los escribe el cliente en Redsys (o en el formulario de Stripe), así que nunca pasan por nuestro servidor (PCI SAQ A).
- El pago solo se da por bueno cuando lo dice la pasarela: por el aviso firmado o por el resultado firmado al volver (Redsys), o por el webhook o la consulta (Stripe).
- Cada aviso se aplica una sola vez.
- Con Stripe, recargar la página de pago reutiliza el mismo cobro. Con Redsys solo se cobra en su página, y un segundo cobro deja el pedido «a revisar».
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
- **Detalle**: piezas, importes con IVA, cobro, plazo y dirección.
- **Notas del pedido** (ADR 0025), como en WooCommerce:
  - «Añadir nota» deja una **nota privada** (solo el equipo) o una **nota para el cliente**.
  - La nota para el cliente le llega por email y la ve en su pedido, en «Actualizaciones del pedido».
  - Debajo sale todo lo que apunta el sistema, con quién lo hizo.
- **Acciones del pedido**:
  - Enviar los detalles del pedido al cliente.
  - Reenviar la notificación de nuevo pedido.
  - Ver el pedido como el cliente.
  - Orden de fabricación y albarán en PDF.
- El pedido no tiene botón de editar ni de borrar.
- Tiene pestañas: Por fabricar, En fabricación, Para enviar, Enviados y A revisar.
- La ficha enseña las devoluciones del pedido, con enlace a cada una.

## Antes de vender de verdad

- [ ] Textos legales revisados por la asesoría. Se generan solos al guardar los datos fiscales (ADR 0020) y se marcan como revisados en *Ajustes → Textos legales*. Cada pedido guarda la versión de las condiciones que aceptó.
- [ ] Datos fiscales de Clave de Alma en el pie y en los emails. Las facturas llegan en la fase 6.
- [ ] Decisión fiscal sobre Canarias, Ceuta y Melilla, y sobre el umbral de la UE (OSS).
- [ ] Tarifas de envío reales en el panel.
- [ ] TPV virtual de Redsys contratado con el banco (con Bizum) y sus datos reales con `REDSYS_ENVIRONMENT=live`, **solo en producción**. La URL de notificación, dada al banco.
- [ ] Correo de salida (SMTP) configurado y probado.
- [ ] Dirección de devoluciones (`RETURNS_ADDRESS`).
