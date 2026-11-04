# Facturación (Veri*Factu)

Cada venta cobrada genera su **factura simplificada**. Cada devolución de dinero, por cancelación o por devolución de piezas, genera su **factura rectificativa**. No hay que hacer nada a mano.

## Antes de facturar: datos fiscales

En *Administración → Datos de facturación* → **Editar datos fiscales** se rellenan:
- razón social, NIF y domicilio fiscal de Clave de Alma;
- email de facturación (opcional);
- **series**: por ejemplo, `T` para las simplificadas y `R` para las rectificativas. No se pueden cambiar una vez emitida la primera factura.

Mientras falte algún dato:
- **no se emiten facturas**;
- cada pedido cobrado lo anota en su historial («Factura pendiente»);
- al completar los datos, el botón **Emitir facturas pendientes** (o el comando `php artisan facturas:emitir-pendientes`) las emite todas, en orden.

Los datos se copian en cada factura al emitirla. Cambiarlos después no altera las facturas ya emitidas.

## Cómo son las facturas

| | Factura simplificada | Rectificativa |
| --- | --- | --- |
| Cuándo | Al confirmarse el cobro de un pedido | Al devolver dinero (cancelación o devolución) |
| Número | `T2026-000001`, `T2026-000002`… | `R2026-000001`… |
| Importe | Piezas + envío, IVA incluido y desglosado | En negativo, por lo devuelto, repartido por tipos de IVA |

- Las **compras de prueba** no se facturan.
- Una factura **no se edita ni se borra**. Un error se corrige con una rectificativa.
- **El cliente** descarga sus facturas en PDF desde la página de su pedido (el enlace «Ver mi pedido» de sus emails).
- **En el panel** están en *Ventas → Facturas* y también enlazadas desde cada pedido.

## Veri*Factu

Cada factura lleva su **registro de facturación**:
- una **huella** (SHA-256) encadenada con la del registro anterior, que impide alterar o borrar facturas sin que se note;
- un **código QR** de cotejo con la AEAT y la leyenda **VERI*FACTU**, al principio del PDF.

El **envío de los registros a la AEAT** necesita el **certificado digital de Clave de Alma**. Hasta tenerlo, los registros se guardan y aparecen como «Sin enviar». Cuando esté:
1. Se integra el driver `aeat` contra el entorno de pruebas de la AEAT y se valida.
2. Se activa en el `.env` con `VERIFACTU_DRIVER=aeat` y `VERIFACTU_PRODUCTION=true`, solo en producción.
3. `php artisan verifactu:enviar` (cada hora, en el programador) envía todos los registros pendientes, también los antiguos, en orden.

Con `VERIFACTU_PRODUCTION=false` el QR apunta al entorno de pruebas de la AEAT.

## Pendiente de la gestoría

- [ ] Datos fiscales y series definitivas.
- [ ] Fecha desde la que Clave de Alma está obligada a Veri*Factu y certificado digital.
- [ ] Si se ofrece **factura completa** con el NIF del cliente a quien la pida (canje de la simplificada).
