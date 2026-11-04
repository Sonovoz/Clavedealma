# ADR 0010 · Tienda activa en el panel, prendas por tienda y envíos por peso

- **Estado:** aceptada
- **Fecha:** 2026-11-05

## Contexto

Tras probar el panel, los fundadores pidieron tres cosas:

1. Que nunca haya duda de en qué tienda se está trabajando.
2. Quitar la pantalla de importación, porque confunde. Los artículos se dan de alta con la ficha, partiendo de la prenda base.
3. Que el envío lo pague el cliente según un baremo por peso, con el transportista elegido por Clave de Alma y envío gratis opcional.

## Decisión

### Tienda activa

- Arriba del panel hay un selector «Tienda: …» (`App\Filament\Livewire\StoreSwitcher`), que se guarda en la sesión (`ActiveStore`).
- Productos, colecciones y prendas base se ven, se abren y se crean solo en la tienda activa (`ScopedToActiveStore`). Abrir por dirección un registro de otra tienda da 404.
- Se eligió un selector propio y no la multitenencia de Filament. La de Filament obliga a que los modelos del catálogo tengan una relación con el modelo `Store` del módulo Stores, y eso rompe la regla de que un módulo no usa clases internas de otro (ADR 0001).

### Prendas base por tienda

- `base_garments.store_id` y código único por tienda. Las prendas existentes pasaron a la tienda de sus artículos.
- Recorrido de alta: prenda base → «Crear artículo con esta prenda».

### Importación

- Sale del panel. El importador queda como herramienta interna (`catalogo:importar`) para cargas grandes, con las mismas reglas que la ficha.
- Las pestañas Prendas y Tallas se guardan en la tienda de la hoja: una hoja, una tienda.

### Envíos (módulo `Shipping`)

- Destinos fijos: Península, Baleares, Canarias, Ceuta y Melilla, Portugal y resto de la UE.
  - Se deducen del país y del código postal (`ZoneResolver`).
  - Canarias, Ceuta y Melilla van aparte por su fiscalidad (IGIC/IPSI), que se resolverá en la fase de pagos.
- Por destino: el transportista elegido (el cliente no lo elige), un baremo por peso con tramos «hasta X kg → precio con IVA», envío gratis desde un importe y el plazo.
- `QuoteShipping` calcula el precio a partir del destino, el peso total y el importe.
  - El peso sale de «Peso para el envío» de cada prenda base.
  - El baremo exacto lo definirá Clave de Alma en el panel, *Ajustes → Zonas de envío*. Nada viene inventado: los destinos nacen desactivados y sin tarifa.

## Consecuencias

- La cesta y el pago (fase 4) usarán `QuoteShipping`. Si un destino no tiene tarifa, no se podrá comprar con envío allí.
- Si algún día hace falta cobrar por volumen, se añadirá a la prenda base y el baremo pasará a calcular con el peso volumétrico. El contrato `ShippingQuote` no cambia.
