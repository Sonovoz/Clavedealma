# ADR 0022 · Inventario por producto, como WooCommerce

- Estado: aceptado (sustituye al stock por variante de los ADR 0006, 0007 y 0008)
- Fecha: 2026-11-05

## Contexto

Clave de Alma **fabrica bajo pedido**. El stock por talla y color con tres modos de venta («bajo pedido», «limitada» y «stock y después bajo pedido») y la pantalla *Productos → Inventario* eran más de lo que hace falta. El equipo necesita dos cosas, como en WooCommerce:

- **dejar de vender un producto por ahora**, por ejemplo cuando faltan prendas base;
- a veces, **limitar las unidades** (ediciones especiales).

## Decisión

- **El inventario es del producto**, no de cada talla. En la ficha hay una pestaña **«Inventario»** con los nombres de WooCommerce:
  - **Estado del inventario**: *Hay existencias* (se vende y se fabrica bajo pedido) o *Agotado* (no se vende por ahora; la ficha se ve, pero no se puede añadir al carrito).
  - **¿Gestionar inventario?**: si se marca, **Cantidad en inventario** limita las unidades que se pueden vender entre todas las tallas y colores. Al llegar a 0 sale «Agotado» solo.
  - **Umbral de pocas existencias**: se marca en el listado.
- **Listado de productos**: columna «Inventario» («Hay existencias», «Hay existencias (12)», «Agotado») y acciones masivas «Marcar como agotado» / «Marcar con existencias».
- **Se quita *Productos → Inventario***. Si una talla concreta no se puede hacer, se desactiva en la tabla de variantes de la ficha.
- **Módulo Inventory**:
  - `product_stocks`: estado, gestionar, cantidad y umbral. Sin fila = hay existencias, sin límite.
  - `product_stock_movements`: inmutable.
  - `ConfigureStock` (ficha e importador) y `AdjustStock`.
  - `RecordSale`, `RestockCancelledSale` y `RestockReturn` agrupan por producto con el contrato `VariantLookup` del catálogo.
  - `StockAvailability::productLimits()` da el límite por producto.
- **Carrito y tienda**:
  - `PriceCart` reparte el límite entre las tallas del carrito.
  - La tarjeta y la ficha muestran «Agotado».
  - Los datos para Google dicen `MadeToOrder` salvo con cantidad limitada.
- **La ficha no pisa lo vendido**: la cantidad se guarda como diferencia sobre la que había al abrirla. El importador solo fija la cantidad mientras no haya ventas; si las hay, avisa.
- **Importador**: las columnas `venta`, `unidades` y `aviso_stock` siguen, pero son del producto entero.
  - `venta` admite `bajo pedido`, `agotado` o `limitada`.
  - `stock y bajo pedido` se lee como `bajo pedido`, con aviso.
- **Migración**:
  - un producto con variantes «limitadas» pasa a gestionar inventario con la suma de sus unidades;
  - el resto, sin límite;
  - el historial por variante no se traslada, porque el proyecto aún no vendía: queda un alta inicial con la cantidad resultante.

## Consecuencias

- El fabricante ya no tiene pantalla de stock: trabaja en *Comercio → Fabricación*.
- Si un día hace falta stock por talla (como las variaciones de WooCommerce), se añade una cantidad opcional por variante encima de esto, sin cambiar el estado del producto.
