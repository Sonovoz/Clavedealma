# ADR 0008 · Ficha de artículo, ficha textil y precio de referencia de las rebajas

- **Estado:** aceptada
- **Fecha:** 2026-11-05

## Contexto

Las fichas de producto de moda muestran siempre lo mismo:

- nombre y color;
- referencia;
- precio, con el precio tachado si hay rebaja;
- tallas en una escala fija y guía de tallas;
- muestras de color;
- composición y cuidados.

Además, el equipo interno necesita dar de alta artículos desde el panel sin preparar una hoja. Los artistas no editan nada.

## Decisión

1. **Ficha de artículo por pestañas** en el panel: General, Tallas y colores, Fotos, Precio y fabricación, y Publicación.
   - En *Tallas y colores* hay un generador que crea todas las combinaciones (`Catalog\Application\GenerateVariants`). Al editar, solo añade las que faltan.
2. **Una sola fuente de reglas para la ficha y para la hoja:**
   - `Catalog\Application\PublicationCheck`: lo mínimo para publicar.
   - `Inventory\Application\SetStockMode`: modo de venta y unidades, sin pisar el stock vivo.
   - `Catalog\Domain\SizeScale`: escala XS…3XL, alias y orden.
   - La coordinación entre módulos para la ficha está en `App\Platform\Catalog\ArticleEditor`, en la capa externa.
3. **La ficha textil vive en la prenda base.** Incluye composición, corte, cuidados, país de fabricación y guía de tallas (`garment_sizes`, en cm). Los artículos la heredan.
   - La composición es obligatoria para publicar (Reglamento UE 1007/2011).
4. **Precio de referencia de las rebajas calculado, nunca escrito a mano.**
   - Cada cambio del precio efectivo de una variante crea una fila inmutable en `variant_prices`.
   - El precio tachado es el más bajo aplicado en los 30 días anteriores a la bajada (`Catalog\Domain\ReferencePrice`, art. 20 de la Ley 7/1996, directiva Ómnibus). Si el precio no baja respecto a ese mínimo, no se muestra rebaja.
5. **Muestra de tejido:** una foto marcada `is_swatch` por color. En la hoja es la foto `MODELO_COLOR_00`.
6. **Rol «gestor de catálogo»** (`gestor_catalogo`), interno:
   - gestiona el catálogo de todas las tiendas e importa (`CatalogPolicy`);
   - no accede a artistas (datos fiscales e IBAN), tiendas, usuarios, sistema ni stock;
   - solo el superadministrador borra definitivamente.
   - Las cuentas se crean en *Administración → Usuarios*.

## Consecuencias

- Las instalaciones existentes reciben el rol con una migración, que aplica «Aplicar actualización» en Hostinger.
- Lo que se publique en la tienda pública (fase 3) toma el tachado de `ProductVariant::discount()` y nunca lo calcula por su cuenta.
- El recomendador «¿Cuál es mi talla?» queda para la capa visual (fase 8) y se apoyará en la guía de tallas.
