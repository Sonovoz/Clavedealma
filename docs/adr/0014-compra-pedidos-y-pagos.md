# ADR 0014 · Compra: cesta única, pedidos inmutables y pago por contrato

- Estado: aceptado
- Fecha: 2026-11-04

## Contexto

Clave de Alma vende y cobra todas las piezas de todas las tiendas. Hace falta comprar en modo test con Stripe, sin romper las reglas del monolito modular, y poder probar sin claves.

## Decisión

**Módulos nuevos**:
- `Cart`: cesta en la sesión que guarda solo variante y unidades. `PriceCart` la recalcula con los datos del momento.
- `Orders`: pedido, líneas e historial.
- `Payments`: pasarela, cobros y avisos.

Se comunican por contratos y eventos:

| Contrato o evento | De | Para |
| --- | --- | --- |
| `Catalog\Contracts\VariantLookup` → `SellableVariant` | Catálogo | Cesta |
| `Inventory\Contracts\StockAvailability` | Inventario | Cesta |
| `Stores\Contracts\StoreDirectory` → `StoreInfo` | Tiendas | Cesta, Pedidos |
| `Shipping\Contracts\ShippingQuoter` | Envíos | Compra |
| `Payments\Contracts\Events\PaymentSucceeded` / `PaymentFailed` | Pagos | Pedidos |
| `Orders\Contracts\Events\OrderPaid` | Pedidos | Inventario (descuenta stock), emails |
| `Orders\Contracts\Events\OrderCancelled` | Pedidos | Pagos (anula el cobro) |
| `Inventory\Contracts\Events\StockShortage` | Inventario | Pedidos («a revisar») |

**Pedido inmutable**:
- Las líneas copian lo vendido: precio con IVA, tipo de IVA, comisión y peso.
- Los importes y los datos del cliente no se pueden cambiar, y el modelo lo impide.
- Solo cambian el estado, `review_reason` y las fechas de pago y cancelación. Cada cambio deja una fila en `order_events`.

**Pago**:
- Contrato `PaymentGateway` con dos drivers: `stripe` (Payment Element y webhook firmado) y `fake` (pago simulado, prohibido en producción).
- La fuente de verdad del pago es la pasarela: el webhook, o la consulta del cobro al volver del pago.
- Cada aviso se aplica una vez gracias a `payment_webhook_events`, con clave única.
- La clave de idempotencia es la misma para un pedido y un importe: recargar no crea otro cobro.

**Stock**:
- Se descuenta al pagar, no al añadir a la cesta. Así no hay reservas que caduquen.
- Un faltante por compras simultáneas deja el pedido «a revisar» en lugar de perder el cobro.

**Destinos**: Canarias, Ceuta y Melilla quedan cerradas hasta resolver IGIC, IPSI y aduana.

**Cuentas de cliente**: no hay. Se compra como invitado con email. Las cuentas podrán llegar más adelante.

## Consecuencias

- Las páginas de compra son comunes, con la imagen de Clave de Alma. Ningún diseño de tienda calcula ni muestra importes de la cesta por su cuenta.
- Las facturas (fase 6) se emitirán a partir del pedido, que ya guarda el IVA por línea y del envío.
- Bizum no está en Stripe de forma nativa. Si hace falta, será otro driver del mismo contrato.
