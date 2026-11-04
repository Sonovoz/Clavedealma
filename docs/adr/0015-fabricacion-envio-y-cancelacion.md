# ADR 0015 · Fabricación, envío y cancelación con devolución del importe

- Estado: aceptado
- Fecha: 2026-11-04

## Decisión

**Estados del pedido** (`OrderStatus`) y pasos permitidos (`Orders\Domain\OrderFlow`):

```text
pendiente de pago → pagado → en fabricación → listo para enviar → enviado → entregado
        ↘ cancelado        ↘──────── cancelado (devolviendo el importe) ───────┘
```

- **No hay saltos ni vuelta atrás.** Cada paso guarda su fecha y deja constancia en el historial de quién lo hizo.
- **Quién hace cada paso:**
  - El **fabricante** empieza la fabricación y marca el pedido como listo, desde *Comercio → Fabricación*. En esa pantalla no ve ningún dato del cliente (minimización de datos, RGPD).
  - **Clave de Alma** (superadministrador) lo envía con transportista y número de seguimiento, y lo marca entregado. También puede hacer los pasos del fabricante.
- **El enlace de seguimiento** se forma con la plantilla del transportista (`{codigo}`, en *Ajustes → Transportistas*) o se escribe a mano.
- **Al enviar** se avisa al cliente (`OrderShipped`). Su página del pedido enseña los pasos y el seguimiento.
- **Cancelar un pedido pagado:**
  - Solo se puede mientras no haya salido.
  - Primero se devuelve todo lo cobrado, por el contrato `Payments\Contracts\Refunds`. Si la devolución falla, el pedido **no** se cancela.
  - Después, `PaidOrderCancelled` hace dos cosas:
    - Inventario repone exactamente las unidades que salieron del stock al pagar.
    - Se avisa al cliente con el motivo.
- **Devoluciones de dinero** (`payment_refunds`):
  - Nunca superan lo cobrado menos lo ya devuelto.
  - La misma clave nunca devuelve dos veces.
  - Tras un intento fallido, el reintento usa una clave nueva ante la pasarela.

## Consecuencias

- Las devoluciones de piezas (fase 5b) usarán el mismo contrato `Refunds`.
- El pedido sigue siendo inmutable: solo cambian el estado, las fechas y el seguimiento. Los importes devueltos viven en Pagos, no en el pedido.
