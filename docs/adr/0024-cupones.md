# ADR 0024 · Cupones, como WooCommerce

- Estado: aceptado
- Fecha: 2026-11-05

## Decisión

- **Módulo `Promotions`**: el cupón (`Coupon`) y sus usos (`CouponRedemption`), con el contrato `Coupons::evaluate()`.
  - Las reglas, sin framework: `Domain\CouponPolicy` y `Domain\DiscountAllocator`.
  - En el panel, ***Marketing → Cupones*** (solo superadmin), con los campos y las pestañas de WooCommerce:
    - **General**: tipo de descuento (porcentual o fijo en el carrito), importe, envío gratuito, válido desde y fecha de caducidad.
    - **Restricción de uso**: gasto mínimo, una tienda o todas, y excluir los artículos en oferta.
    - **Límites de uso**: por cupón y por usuario (email).
  - Crear o cambiar un cupón queda en el registro de seguridad.
  - Un cupón ya usado no se borra: se desactiva.
- **En la tienda, como WooCommerce**:
  - En el carrito, «Código de cupón» + **Aplicar cupón**, y en los totales la fila «Cupón: CÓDIGO −X € [Quitar]».
  - En «Finalizar compra», «¿Tienes un cupón? Haz clic aquí para introducir tu código».
  - Un cupón por pedido.
  - Si deja de valer (el carrito cambió, caducó, se agotó…), se quita y se dice por qué.
- **El descuento se reparte en las líneas del pedido**, al céntimo y en proporción a su importe (`order_lines.discount_cents`). El `total_cents` de cada línea ya va descontado.
  - El pedido guarda `coupon_code` y `discount_cents`.
  - Así salen solos, de lo que de verdad se cobra:
    - el **IVA** (por línea y tipo);
    - la **factura** (cada línea dice «descuento cupón CÓDIGO −X €»);
    - la **comisión del artista** («Mis ventas», informes, liquidación);
    - las **devoluciones** (se devuelve lo pagado por cada pieza, sin perder ni ganar un céntimo entre devoluciones parciales).
  - El envío gratis del cupón deja el envío del pedido a 0.
- **Siempre en el servidor**: el cupón se vuelve a comprobar al mostrar el carrito, al calcular el envío y al realizar el pedido. El límite por cliente se comprueba ahí con su email.
- **Los usos cuentan al pagar** (`OrderPaid` → `RecordRedemption`) y se liberan si el pedido pagado se cancela (`PaidOrderCancelled` → `ReleaseRedemption`). Los pedidos sin pagar no gastan usos.
- **Condiciones de venta**: la plantilla explica cómo funcionan los cupones (no se canjean por dinero, uno por pedido, y en una devolución se reembolsa lo pagado).

## Consecuencias

- El subtotal que se enseña es el anterior al cupón (como en WooCommerce), y el cupón va en su fila. `orders.subtotal_cents` es la suma de las líneas ya descontadas, para que el IVA cuadre.
- Si un día se quieren varios cupones por pedido o descuentos por producto, el reparto por líneas ya lo permite: se sumarían en `discount_cents`.
