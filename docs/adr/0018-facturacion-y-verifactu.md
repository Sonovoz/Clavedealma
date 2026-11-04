# ADR 0018 · Facturación y Veri*Factu

- Estado: aceptado (pendiente de validar con la gestoría y con el entorno de pruebas de la AEAT)
- Fecha: 2026-11-04

## Contexto

Clave de Alma vende a consumidores y debe emitir factura de cada venta y rectificar las devoluciones. El sistema de facturación debe cumplir el Reglamento de sistemas informáticos de facturación (RD 1007/2023, Orden HAC/1177/2024): cada factura tiene un registro con huella encadenada, QR de cotejo y leyenda «VERI*FACTU», y los registros se envían a la AEAT.

## Decisión

- **Módulo `Invoicing`.** Escucha `OrderPaid` (factura) y `Payments\Contracts\Events\RefundIssued` (rectificativa). Lee el pedido por `Orders\Contracts\OrderLookup`.
- **Tipos**: F2, factura simplificada, para ventas a consumidores, y R5, rectificativa de simplificada, en negativo por el dinero devuelto. La rectificativa se reparte entre los tipos de IVA de la factura original y cuadra al céntimo.
- **Numeración**: correlativa por serie y año (`T2026-000001`, `R2026-000001`). Se calcula bajo bloqueo para que no haya huecos ni duplicados. Las series se eligen en el panel y no se cambian después de la primera factura.
- **Emisor**: los datos fiscales se rellenan en *Ajustes → Datos de facturación* y se copian en cada factura. Mientras falten, no se factura: el pedido anota «Factura pendiente» y `facturas:emitir-pendientes` las emite después, en orden.
- **Inmutables**: una factura no se edita ni se borra. Solo cambia su estado de envío a la AEAT.
- **Compras de prueba**: no se facturan, para no gastar numeración.
- **Veri*Factu desactivado hasta que sea obligatorio** (`VERIFACTU_ENABLED=false`, valor por defecto):
  - las facturas salen sin QR ni leyenda y no se envían;
  - la huella encadenada se guarda igualmente;
  - cada factura recuerda si se emitió con Veri*Factu (columna `verifactu`), así que al activarlo solo cambian las nuevas.
- **Veri*Factu activado**:
  - Huella SHA-256 con el formato de la AEAT. La prueba la compara con los dos ejemplos oficiales, el primero y el encadenado.
  - Encadenada con el registro anterior.
  - QR de cotejo: entorno de pruebas o de producción (`VERIFACTU_PRODUCTION`).
  - El QR y la leyenda van al principio del PDF.
- **Envío a la AEAT** por el contrato `VerifactuSender`:
  - Driver `none` (hoy): el registro se guarda y queda «sin enviar».
  - Driver `aeat`: necesita el certificado digital de Clave de Alma y se integrará contra el entorno de pruebas de la AEAT antes de producción.
  - `verifactu:enviar` reenvía lo pendiente.

## Pendiente de confirmar con la gestoría

- Datos fiscales y series.
- Si el cliente puede pedir factura completa con su NIF, que sería un canje de la simplificada.
- Fecha de obligación de Veri*Factu para Clave de Alma y alta del certificado.
