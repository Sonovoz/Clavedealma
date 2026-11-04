# Documentos PDF

El panel genera estos PDF. Se descargan al momento y no se guardan en el servidor.

| PDF | Para qué | Dónde se descarga |
| --- | --- | --- |
| **Hoja de fabricación** | Todo lo pendiente de fabricar: el total por SKU, para fabricar por tandas, y cada pedido con sus piezas, una casilla para marcarlas y la fecha límite (en rojo si se pasa). | *Comercio → Fabricación* (botón arriba de la tabla) y *Análisis → Informes* |
| **Orden de fabricación** | Lo mismo, para un solo pedido. | *Comercio → Fabricación* → «PDF» en la fila, o la ficha del pedido → **PDF** |
| **Albarán de envío** | Va dentro del paquete: dirección, teléfono, piezas con casilla de comprobación, y cómo devolver. **Sin precios.** | Ficha del pedido → **PDF → Albarán de envío** |
| **Estado de los envíos** | Cuántos hay en fabricación, listos para enviar, en camino y con retraso, con el seguimiento de cada uno. | *Comercio → Pedidos* (arriba) y *Análisis → Informes* |
| **Informe de ventas** | Para el periodo elegido: pedidos, piezas, total cobrado, envíos, IVA, dinero devuelto y neto; ventas y comisión estimada por tienda; piezas más vendidas. | *Análisis → Informes → Informe de ventas (PDF)* |

**Privacidad**:
- La hoja y la orden de fabricación **nunca** llevan nombre, email, teléfono ni dirección del cliente, porque las usa el fabricante.
- El albarán sí lleva la dirección, porque va en el paquete.
- Los informes de ventas y de envíos son solo para el superadministrador.

**El informe de ventas es informativo**:
- Cuenta los pedidos por fecha de pago, en hora de Madrid.
- No incluye las compras de prueba, salvo que se marque.
- La comisión de cada artista es una **estimación**: sale del porcentaje guardado en cada venta, sobre el precio sin IVA y sin envío, y antes de devoluciones.
- Las facturas y las liquidaciones oficiales llegarán con la fase 6.

*Análisis → Informes* también enseña en pantalla el resumen del mes en curso.

Para desarrolladores: los datos de cada documento están en `App\Platform\Documents\*`, las plantillas en `resources/views/pdf/*` y el motor de PDF va por el contrato `App\Platform\Pdf\PdfRenderer` (ADR 0017).
