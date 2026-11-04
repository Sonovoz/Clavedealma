# ADR 0017 · Documentos PDF del panel

- Estado: aceptado
- Fecha: 2026-11-04

## Decisión

Los PDF se generan en el propio servidor con **dompdf** (`barryvdh/laravel-dompdf`), detrás del contrato `App\Platform\Pdf\PdfRenderer`. No necesita navegador ni servicios externos, así que funciona igual en Hostinger que en Docker. Si algún día hiciera falta más calidad tipográfica, se cambia el driver sin tocar los documentos.

Cada documento separa los datos (`App\Platform\Documents\*`, sin HTML) de la plantilla (`resources/views/pdf/*`), que es CSS sencillo con la fuente DejaVu Sans.

| Documento | Quién | Dónde | Datos del cliente |
| --- | --- | --- | --- |
| Orden de fabricación (un pedido) y hoja de fabricación (todo lo pendiente, con totales por SKU) | Fabricante y superadmin | *Operación → Fabricación*, ficha del pedido | **No** |
| Albarán de envío (va en el paquete) | Superadmin | Ficha del pedido → PDF | Dirección y teléfono. Sin precios |
| Estado de los envíos | Superadmin | *Ventas → Pedidos*, *Ventas → Informes* | Nombre y destino |
| Informe de ventas por periodo | Superadmin | *Ventas → Informes* | No |

**El informe de ventas es informativo.** Cuenta por fecha de pago y no incluye las compras de prueba, salvo que se marque. La comisión es una estimación: sale de la comisión guardada en cada línea, sobre el precio sin IVA y sin envío, y antes de descontar devoluciones. No sustituye a las facturas ni a las liquidaciones de la fase 6.
