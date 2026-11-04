# ADR 0016 · Devoluciones de piezas (derecho de desistimiento)

- Estado: aceptado
- Fecha: 2026-11-04

## Contexto

El cliente puede desistir de la compra en los 14 días naturales siguientes a la entrega, salvo las piezas personalizadas (art. 102 y 103 TRLGDCU). Hay que gestionarlo sin tocar el pedido, que es inmutable.

## Decisión

**Módulo nuevo `Returns`.** Usa solo contratos de otros módulos:
- `Orders\Contracts\OrderLookup`: datos del pedido y anotaciones en su historial.
- `Payments\Contracts\Refunds`: devolver el dinero.

Además emite el evento `ReturnReceived`, que escucha Inventario para reponer las piezas.

**Reglas** (`Returns\Domain\ReturnPolicy`):
- **Plazo**: 14 días desde la entrega marcada. Si no se marcó, se cuenta desde el envío más el plazo máximo de entrega, lo más favorable para el cliente.
- **Qué se puede devolver**: las piezas personalizadas, nada. Las demás, lo comprado menos lo que ya esté en otra devolución no rechazada.
- **Importe**: las piezas devueltas, más el envío pagado si con esta devolución vuelve el pedido entero (art. 107). El envío de vuelta lo paga el cliente, salvo defecto o error nuestro: el panel lo recuerda al aprobar.
- El importe se calcula al pedir la devolución y queda fijado. La devolución es inmutable: solo avanza el estado.

**Pasos**:

```text
pedida → aprobada (instrucciones por email) → recibida (reponer o no) → dinero devuelto
      ↘ rechazada (motivo por email)
```

- **Si la pasarela falla** al devolver el dinero, la devolución queda «recibida» con el error y se puede reintentar. La clave de idempotencia es una por devolución.
- **El cliente** pide la devolución desde la página de su pedido, con el enlace privado de los emails. No hacen falta cuentas.

## Consecuencias

- Las facturas rectificativas de cada devolución llegarán con la facturación (fase 6), a partir de estas devoluciones y de `payment_refunds`.
- Las liquidaciones a artistas (fase 6) descontarán las piezas devueltas.
