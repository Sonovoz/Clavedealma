# ADR 0006 · Los módulos se comunican con eventos

- **Estado:** aceptada
- **Fecha:** 2026-11-04

## Decisión

Cuando un módulo necesita reaccionar a algo que ocurre en otro, el módulo de origen publica un **evento** en su carpeta `Contracts/Events` y el otro lo escucha desde su `ServiceProvider`. Ningún módulo llama a las clases internas de otro.

Primer caso: `Catalog\Contracts\Events\VariantCreated` → `Inventory` crea la ficha de stock de la variante (`CreateStockLevelForVariant`). *Desde el ADR 0022 el inventario es por producto y ese evento ya no lo escucha Inventory.*

## Reglas

- Los eventos se emiten después de confirmar la transacción (`ShouldDispatchAfterCommit`).
- Los listeners son idempotentes (`firstOrCreate`): procesar dos veces el mismo evento no duplica nada.
- La capa de interfaz (Filament) sí puede consultar varios módulos a la vez para mostrar datos (por ejemplo, la tabla de stock une variantes, productos y tiendas), pero nunca contiene reglas de negocio.
