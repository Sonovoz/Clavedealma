# Protocolo de alta de artículos

Cómo se pasan los artículos reales de cada tienda para que salgan con sus fotos, tallas, colores, detalles y precio sin tocar nada a mano. Se usa igual para la primera carga y para los cambios posteriores.

**Qué se entrega por cada tienda:**

1. **La hoja de artículos**, en [`plantilla-articulos.xlsx`](plantilla-articulos.xlsx) (o en .csv con las mismas columnas). Hay una fila por variante.
2. **Un ZIP con las fotos**, nombradas según la regla de abajo.

**Ejemplo completo:** la tienda Farruquito está en [`database/seeders/data/farruquito/`](../../database/seeders/data/farruquito). Tiene 35 artículos, 186 filas y 60 fotos. Es material confidencial del artista, igual que `docs/diseno/farruquito`.

## 1. La hoja: una fila por variante

Una **variante** es cada combinación de talla y color que se puede comprar. Una camiseta en negro y blanco con 6 tallas tiene 12 filas. Una gorra de talla única en 3 colores tiene 3 filas.

Las filas del mismo artículo comparten el **modelo**. Los datos de producto se escriben en la primera fila y en las siguientes se repiten o se dejan vacíos. Si dos filas del mismo modelo tienen datos de producto distintos, la hoja da error.

| Columna | Obligatoria | Nivel | Qué poner | Ejemplo |
| --- | --- | --- | --- | --- |
| `tienda` | sí | producto | Subdominio de la tienda. La tienda tiene que existir antes en el panel. | `farruquito` |
| `coleccion` | no | producto | Colección. Si no existe, se crea. Varias, separadas por `\|`. Vacía, no se cambia. | `On Stage` |
| `modelo` | sí | producto | Referencia única del artículo en la tienda, con letras, números y guiones. Da nombre a las fotos. | `FQ-OS-01` |
| `nombre` | sí | producto | Nombre comercial. | `La esencia` |
| `prenda` | no | producto | Prenda base, por código o por nombre. Si no existe, se crea. | `FQ-CAM-OS` |
| `color` | no | variante | Color de la variante. Vacío si el artículo no tiene colores. | `Negro` |
| `color_hex` | no | variante | Muestra del color en la tienda, en formato `#RRGGBB`. | `#111111` |
| `talla` | no | variante | Talla. Vacía significa talla única. | `M` |
| `precio` | sí | variante | Precio de venta **con IVA**, en euros. | `39,90` |
| `iva` | no | producto | Tipo de IVA en %. Por defecto, 21. | `21` |
| `comision` | no | producto | Comisión del artista en %, solo para este artículo. Vacía, se aplica la de la tienda. | |
| `descripcion` | no | producto | Texto de la ficha. Cada línea en blanco separa un párrafo. | |
| `plazo` | no | producto | Días de fabricación, en formato `mín-máx`. Por defecto, `5-7`. | `5-7` |
| `venta` | no | variante | `bajo pedido` (por defecto), `limitada` (se agota) o `stock y bajo pedido`. | `limitada` |
| `unidades` | no | variante | Unidades disponibles. Obligatoria si la venta es `limitada` o `stock y bajo pedido`. | `25` |
| `aviso_stock` | no | variante | Número de unidades a partir del cual se avisa al fabricante. | `5` |
| `personalizado` | no | producto | `si` cuando se fabrica con datos del cliente; no tiene derecho de desistimiento. | `no` |
| `estado` | no | producto | `borrador`, `publicado` o `archivado`. Vacío: un artículo nuevo queda en borrador y uno que ya existe no cambia. | `publicado` |
| `sku` | no | variante | Código propio de la variante. Vacío, se genera como `TIENDA-MODELO-TALLA-COLOR`. | |

La pestaña **Instrucciones** de la plantilla repite esta tabla. Las cabeceras no distinguen mayúsculas ni tildes: `Colección` y `coleccion` valen igual. Una columna desconocida no se tiene en cuenta y genera un aviso.

**Precios distintos por variante.** Si, por ejemplo, la XXL cuesta más, basta con poner ese precio en su fila. El producto toma el precio más bajo y las variantes más caras guardan el suyo.

## 2. Las fotos: nombre fijo

| Tipo | Nombre | Ejemplo |
| --- | --- | --- |
| Foto general del artículo | `MODELO_NN.ext` | `FQ-OS-01_01.jpg` |
| Foto de un color concreto | `MODELO_COLOR_NN.ext` | `FQ-IC-02_NEGRO_01.jpg` |

- `NN` es el orden: `01`, `02`, `03`… Primero salen las fotos generales y luego las de cada color, en el orden de los colores en la hoja.
- El color se escribe como en la hoja, sin espacios. Para «Negro lavado» vale `NEGRO-LAVADO`, y no distingue mayúsculas ni tildes.
- Formatos JPG, PNG o WEBP, con un máximo de 8 MB por foto.
- Recomendación: 1600 px por el lado largo, la misma proporción en todas (4:5) y fondo limpio.
- Se pueden usar carpetas dentro del ZIP, porque solo cuenta el nombre del fichero. Los ficheros ocultos del sistema (`.DS_Store`, `__MACOSX`, `Thumbs.db`) no se tienen en cuenta.

Son **errores** que paran la importación:

- un nombre que no sigue el patrón;
- un modelo que no está en la hoja;
- un color que ese modelo no tiene;
- una foto repetida.

Si un artículo queda sin fotos, se avisa.

## 3. Importar

**Desde el panel** (superadmin): *Catálogo → Importar artículos*.

1. Sube la hoja y el ZIP y pulsa **Comprobar**. El sistema hace la importación completa y la deshace, y enseña los errores fila a fila y lo que cambiaría.
2. Corrige y vuelve a comprobar hasta que no haya errores.
3. Pulsa **Importar**. Con un solo error no se cambia nada.

**Desde la consola** (OVH, o en local):

```bash
php artisan catalogo:importar ruta/articulos.xlsx --fotos=ruta/fotos.zip
php artisan catalogo:importar ruta/articulos.xlsx --fotos=ruta/fotos.zip --aplicar
```

`--fotos` acepta un ZIP o una carpeta. Sin `--aplicar` solo comprueba.

Una plantilla vacía y actualizada se descarga desde el botón **Descargar plantilla** del panel o se genera con `php artisan catalogo:plantilla`.

> **Hostinger:** el tamaño del ZIP lo limita PHP (`upload_max_filesize` y `post_max_size`, en hPanel → PHP). Si las fotos no caben, se puede dividir el ZIP por colecciones e importar la hoja varias veces.

## 4. Reglas al volver a importar

La hoja manda, pero sin destruir nada:

| Qué | Qué pasa |
| --- | --- |
| Artículo | Se identifica por `tienda` + `modelo`. Si ya existe, se actualiza; no se duplica. Su dirección web (slug) no cambia aunque cambie el nombre. |
| Variante | Se identifica por `sku`. Las que ya no están en la hoja se **desactivan**, no se borran, porque pueden tener pedidos. |
| Fotos | Las fotos del ZIP **sustituyen** a las del modelo. Un modelo que no tiene fotos en el ZIP conserva las suyas. |
| Stock | Las `unidades` se aplican mientras la variante solo tenga altas iniciales, es decir, sin ventas, fabricación ni ajustes, o cuando cambia su modo de venta. Si hay stock vivo, la hoja no lo pisa: se avisa y se ajusta en *Operación → Stock*. |
| Colecciones y estado | Si la celda está vacía, no se cambian. |
| Artículo eliminado | Si vuelve en la hoja, se recupera y se avisa. |

Las decisiones técnicas están en [ADR 0007](../adr/0007-protocolo-de-articulos.md).
