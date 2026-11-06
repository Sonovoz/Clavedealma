# ADR 0029 · Minimedia (comisión del 6 %) y correo por SMTP en todo el sitio

- Estado: aceptado
- Fecha: 2026-11-06

## Contexto

Minimedia cobra una comisión del 6 % sobre las ventas de todas las tiendas del portal. Necesita un apartado propio en el panel y un resumen por email cada día 1 de mes a `info@minimedia.es`. Además, todos los correos de la web tienen que salir por SMTP.

## Decisión

### Minimedia

- **Base del 6 %** (`App\Platform\Minimedia\MinimediaCommission`):
  - Se parte de lo vendido en piezas, **sin IVA ni envío** y ya con los cupones aplicados, de los pedidos cobrados en el mes (fecha de pago, hora de Madrid).
  - Se resta **lo devuelto en el mes**: la parte de piezas, sin IVA, del dinero devuelto en ese mes. El envío solo se devuelve cuando vuelve todo, así que cada devolución descuenta primero piezas y el resto es envío.
  - Las compras de prueba no cuentan.
  - Es la misma base que la comisión de los artistas.
- **Cierre mensual inmutable** (`MinimediaStatement`, tabla `minimedia_statements`):
  - Se guardan las cifras del mes, el porcentaje aplicado y el desglose por tienda.
  - Un mes cerrado no cambia: una devolución posterior cuenta en el mes en que se hace.
- **Email del día 1** (`minimedia:cierre`, programado cada día a las 08:20 de Madrid):
  - Cierra el mes anterior y envía el resumen a Minimedia, con el desglose por tienda, el PDF y el CSV.
  - Si el envío falla, el cierre queda guardado y se reintenta cada mañana. El panel lo avisa.
  - El resumen no es una factura.
- **Panel *Minimedia → Comisión Minimedia*** (solo superadministrador):
  - lo que va del mes, por tienda;
  - los cierres, con PDF, CSV y «Enviar»;
  - «Cerrar <mes>», por si no se hizo solo;
  - **Ajustes**: el porcentaje (6 % por defecto) y el email (`info@minimedia.es` por defecto). Piden la contraseña y quedan en el registro de seguridad. Un cambio de porcentaje vale para los meses que se cierren después.

### Correo por SMTP

- **Todos los emails salen por SMTP**: pedidos, envíos, notas, facturas, devoluciones, «Mi cuenta», avisos de artistas, nuevo pedido al equipo, copias, errores y Minimedia. Las plantillas de servidor llevan `MAIL_MAILER=smtp`.
- **Los datos se escriben en el panel**, en *Ajustes → Correo* (`App\Platform\Mail\SmtpSettings`): servidor, puerto, cifrado (SSL/TLS o STARTTLS), usuario, contraseña y remitente.
  - La contraseña se guarda cifrada con la `APP_KEY` y no se vuelve a mostrar.
  - Cada cambio pide la contraseña y queda en el registro de seguridad.
  - Lo del panel manda sobre el `.env` y se aplica al crear el correo (`beforeResolving('mail.manager')`).
- **«Enviar email de prueba»** comprueba que el correo sale y llega.

## Consecuencias

- Si cambia la `APP_KEY`, hay que volver a escribir la contraseña del correo, igual que la del TPV.
- En local (`MAIL_MAILER=log`) los emails se guardan en `storage/logs`, y el panel lo avisa.
