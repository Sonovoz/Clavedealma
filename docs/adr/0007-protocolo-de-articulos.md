# ADR 0007 · Alta de artículos con hoja y fotos de nombre fijo

- **Estado:** aceptada
- **Fecha:** 2026-11-04

## Contexto

Cada tienda de artista tendrá decenas de artículos, cada uno con varias tallas, colores y fotos. Darlos de alta a mano en el panel es lento y da errores, sobre todo al asignar las fotos de cada color. Necesitamos un protocolo fijo que el equipo de producto pueda preparar sin conocer el sistema.

## Decisión

El protocolo se describe en `docs/protocolo/README.md`:

- Hay **una hoja (.xlsx o .csv) con una fila por variante**. Las columnas son fijas y se definen en `App\Platform\CatalogImport\Columns`.
- Las fotos van en **un ZIP** y se llaman `MODELO_NN` o `MODELO_COLOR_NN`.
- `products.reference` guarda el **modelo**, que es único en cada tienda. Es la clave del artículo en la hoja.
- `product_images.color` asocia cada foto a un color.

El importador está en `app/Platform/CatalogImport`, en la capa externa, porque usa varios módulos: Stores, Catalog e Inventory. Lo usan el panel (*Importar artículos*), el comando `catalogo:importar` y `FarruquitoSeeder`, que da de alta la tienda de ejemplo con el mismo protocolo.

## Reglas

- **Comprobar es hacer y deshacer.** Sin `--aplicar`, la importación entera corre dentro de una transacción que se revierte. Así el informe coincide con lo que hará la importación de verdad.
- **Todo o nada.** Con un solo error no se aplica ningún cambio. Los avisos no paran la importación.
- **Repetible.** Los artículos se identifican por tienda y modelo, y las variantes por SKU.
  - Las variantes que faltan se desactivan, no se borran, porque pueden tener pedidos (los pedidos son inmutables).
- **Las fotos se guardan con una huella del contenido en la ruta.** Una foto nueva cambia de dirección, así que ninguna caché sirve la vieja. Reimportar la misma foto no duplica ficheros. El importador solo borra las fotos que él mismo creó.
- **El stock vivo manda.** Las unidades de la hoja solo se aplican cuando la ficha no tiene más movimientos que altas iniciales, o cuando cambia el modo de venta.
  - Se aplican después de confirmar la transacción, porque la ficha de stock nace con el evento `VariantCreated` (ADR 0006).
- **Dinero sin `float`.** Una celda numérica de Excel se convierte a texto con dos decimales y se interpreta con `Money::parse` (ADR 0005).
- **ZIP seguro.**
  - Solo se usa el nombre de cada fichero, nunca su ruta, así que no puede escribir fuera de la carpeta temporal.
  - Se limita el tamaño de cada foto y el total descomprimido.
  - La carpeta temporal se borra al terminar.

## Consecuencias

- Los datos reales de cada tienda llegarán en este formato. Cambiar una columna obliga a actualizar `Columns`, la plantilla (`php artisan catalogo:plantilla`) y la guía.
- La lectura de hojas usa `openspout/openspout`, en PHP puro. Funciona en Hostinger sin extensiones especiales, más allá de `zip`.
