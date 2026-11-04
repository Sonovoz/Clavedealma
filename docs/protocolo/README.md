# Protocolo de alta de artículos

Cómo se pasan los artículos reales de cada tienda para que salgan con sus fotos, tallas, colores, detalles y precio sin tocar nada a mano. Se usa igual para la primera carga y para los cambios posteriores.

Hay dos caminos, con **las mismas reglas**:

- **La ficha del panel** (*Catálogo → Productos*), para altas sueltas y cambios. Ver el apartado 5.
- **La hoja**, para cargas grandes (una colección entera). Es lo que se describe a continuación.

**Qué se entrega por cada tienda:**

1. **La hoja de artículos**, en [`plantilla-articulos.xlsx`](plantilla-articulos.xlsx) (o en .csv con las mismas columnas). Tiene tres pestañas:
   - **Artículos**: una fila por variante.
   - **Prendas**: la ficha textil de cada prenda base (opcional).
   - **Tallas**: la guía de tallas (opcional).
2. **Un ZIP con las fotos**, nombradas según la regla de abajo.

**Ejemplo completo:** la tienda Farruquito está en [`database/seeders/data/farruquito/`](../../database/seeders/data/farruquito). Tiene 35 artículos, 186 filas, 8 prendas y 60 fotos. La composición y los cuidados son **de ejemplo**, y la guía de tallas está vacía hasta tener las medidas reales. Es material confidencial del artista, igual que `docs/diseno/farruquito`.

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
| `talla` | no | variante | Talla de la escala fija: `XS`, `S`, `M`, `L`, `XL`, `XXL`, `3XL`. `2XL` se lee como `XXL`. Vacía significa talla única. | `M` |
| `precio` | sí | variante | Precio de venta **con IVA**, en euros. | `39,90` |
| `iva` | no | producto | Tipo de IVA en %. Por defecto, 21. | `21` |
| `comision` | no | producto | Comisión del artista en %, solo para este artículo. Vacía, se aplica la de la tienda. | |
| `descripcion` | no | producto | Texto de la ficha. Cada línea en blanco separa un párrafo. | |
| `info_modelo` | no | producto | Información del modelo de las fotos. | `El modelo mide 1,80 m y lleva una M.` |
| `plazo` | no | producto | Días de fabricación, en formato `mín-máx`. Por defecto, `5-7`. | `5-7` |
| `venta` | no | variante | `bajo pedido` (por defecto), `limitada` (se agota) o `stock y bajo pedido`. | `limitada` |
| `unidades` | no | variante | Unidades disponibles. Obligatoria si la venta es `limitada` o `stock y bajo pedido`. | `25` |
| `aviso_stock` | no | variante | Número de unidades a partir del cual se avisa al fabricante. | `5` |
| `personalizado` | no | producto | `si` cuando se fabrica con datos del cliente; no tiene derecho de desistimiento. | `no` |
| `estado` | no | producto | `borrador`, `publicado` o `archivado`. Vacío: un artículo nuevo queda en borrador y uno que ya existe no cambia. | `publicado` |
| `sku` | no | variante | Código propio de la variante. Vacío, se genera como `TIENDA-MODELO-TALLA-COLOR`. | |

La pestaña **Instrucciones** de la plantilla repite esta tabla. Las cabeceras no distinguen mayúsculas ni tildes: `Colección` y `coleccion` valen igual. Una columna desconocida no se tiene en cuenta y genera un aviso.

**Precios distintos por variante.** Si, por ejemplo, la XXL cuesta más, basta con poner ese precio en su fila. El producto toma el precio más bajo y las variantes más caras guardan el suyo.

**Rebajas.** El precio tachado no se escribe en ningún sitio: basta con bajar el `precio`. El sistema guarda el historial de precios de cada variante y muestra como precio anterior **el más bajo de los 30 días previos** a la bajada, como exige la Ley de Ordenación del Comercio Minorista (art. 20). Si el precio no baja respecto a ese mínimo, no se muestra rebaja.

### Pestaña Prendas: ficha textil

Una fila por prenda base. Se escribe una vez y la heredan todos los artículos de esa prenda. Las celdas vacías no cambian el dato que ya hubiera.

| Columna | Obligatoria | Qué poner | Ejemplo |
| --- | --- | --- | --- |
| `codigo` | sí | Código de la prenda: es el que va en la columna `prenda` de los artículos. | `FQ-CAM-OS` |
| `nombre` | si es nueva | Nombre de la prenda. | `Camiseta oversize 280 g` |
| `tejido` | no | Tejido y gramaje. | `Algodón 280 g/m²` |
| `composicion` | para publicar | Composición. **Obligatoria para vender textil** (Reglamento UE 1007/2011): sin ella, sus artículos no se publican. | `100 % algodón` |
| `corte` | no | `Regular`, `Oversize`, `Boxy`, `Slim`, `Relaxed`… | `Oversize` |
| `cuidados` | no | Instrucciones de lavado. | `Lavar a 30 °C del revés` |
| `pais` | no | País de fabricación. | `España` |
| `peso` | no | Peso de la prenda en gramos (sirve para el envío). | `280` |
| `nota_tallas` | no | Nota que sale bajo la guía de tallas. | `Medidas tomadas en plano.` |

### Pestaña Tallas: guía de tallas

Una fila por prenda y talla, con las medidas **en cm tomadas en plano**. Si una prenda aparece en esta pestaña, su guía se sustituye entera por estas filas.

| Columna | Obligatoria | Qué poner | Ejemplo |
| --- | --- | --- | --- |
| `prenda` | sí | Código de la prenda. | `FQ-CAM-OS` |
| `talla` | sí | Talla de la escala. | `M` |
| `pecho` | no | Ancho de pecho. | `56` |
| `largo` | no | Largo total. | `74` |
| `manga` | no | Largo de manga. | `22,5` |

## 2. Las fotos: nombre fijo

| Tipo | Nombre | Ejemplo |
| --- | --- | --- |
| Foto general del artículo | `MODELO_NN.ext` | `FQ-OS-01_01.jpg` |
| Foto de un color concreto | `MODELO_COLOR_NN.ext` | `FQ-IC-02_NEGRO_01.jpg` |
| Muestra de tejido de un color (el botón para elegirlo) | `MODELO_COLOR_00.ext` | `FQ-IC-02_NEGRO_00.jpg` |

- `NN` es el orden: `01`, `02`, `03`… Primero salen las fotos generales y luego las de cada color, en el orden de los colores en la hoja.
- `00` está reservado a la muestra de tejido y siempre lleva color.
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

**Desde el panel** (superadmin y gestor de catálogo): *Catálogo → Importar artículos*.

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

## 4. Qué hace falta para publicar

Un artículo con `estado` = `publicado` tiene que tener:

- precio mayor que cero;
- al menos una variante activa;
- al menos una foto;
- una prenda base con composición.

Si a un artículo publicado le falta algo, la importación da error y no se aplica nada. La ficha del panel, en ese caso, lo guarda como borrador y explica qué falta.

## 5. La ficha del panel

*Catálogo → Productos → Crear* (superadmin y gestor de catálogo). Está organizada en pestañas:

| Pestaña | Qué tiene |
| --- | --- |
| General | Tienda, colecciones, modelo, nombre, prenda base (con aviso si le falta composición o guía), descripción e información del modelo. |
| Tallas y colores | Se eligen los colores (con su muestra) y las tallas de la escala, o talla única. Al guardar se crean todas las combinaciones con su tipo de venta. Al editar, solo se añaden las que faltan. Cada variante se retoca en la tabla «Variantes» (precio propio, SKU, a la venta). |
| Fotos | Se suben, se ordenan arrastrando y se asignan a un color; una por color puede ser su muestra de tejido. |
| Precio y fabricación | Precio con IVA, IVA, rebaja en curso (calculada), comisión propia, plazo, personalización y archivo de producción. |
| Publicación | Estado y, si falta algo para publicar, qué es. |

La composición, los cuidados y la guía de tallas se rellenan en *Catálogo → Prendas base*.

## 6. Reglas al volver a importar

La hoja manda, pero sin destruir nada:

| Qué | Qué pasa |
| --- | --- |
| Artículo | Se identifica por `tienda` + `modelo`. Si ya existe, se actualiza; no se duplica. Su dirección web (slug) no cambia aunque cambie el nombre. |
| Variante | Se identifica por `sku`. Las que ya no están en la hoja se **desactivan**, no se borran, porque pueden tener pedidos. |
| Fotos | Las fotos del ZIP **sustituyen** a las del modelo. Un modelo que no tiene fotos en el ZIP conserva las suyas. |
| Prendas | Las celdas vacías de la pestaña Prendas no cambian nada. Una prenda que sale en la pestaña Tallas recibe esa guía entera. |
| Stock | Las `unidades` se aplican mientras la variante solo tenga altas iniciales, es decir, sin ventas, fabricación ni ajustes, o cuando cambia su modo de venta. Si hay stock vivo, la hoja no lo pisa: se avisa y se ajusta en *Operación → Stock*. |
| Colecciones y estado | Si la celda está vacía, no se cambian. |
| Artículo eliminado | Si vuelve en la hoja, se recupera y se avisa. |

Las decisiones técnicas están en [ADR 0007](../adr/0007-protocolo-de-articulos.md).
