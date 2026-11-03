# Prototipos de diseño

Referencia visual para la fase 8 (capa visual). Son páginas HTML estáticas: se abren directamente en el navegador, sin instalar nada. **No forman parte de la aplicación**: la tienda real se construirá en Blade sobre la plantilla común y los tokens de cada tienda.

| Carpeta | Qué es | Origen |
| --- | --- | --- |
| [`matriz/`](matriz/index.html) | Web matriz de Clave de Alma: foco interactivo, tiendas, merchan dinámico, drop de ejemplo, The Club | Inspiration Book v4.0 y dosier del Club v2.0 |
| [`farruquito/`](farruquito/index.html) | Primera tienda: Serás Farruquito · Colección On Stage | Libro de colección "Serás Farruquito" |

## Uso

- Abrir `index.html` de cada carpeta con el navegador (mejor en ordenador: muchos efectos responden al cursor).
- Precios, fechas de agenda, ediciones limitadas y formularios son **de ejemplo**: nada se envía ni se cobra.
- Las fuentes se cargan de Google Fonts solo en el prototipo; en la aplicación irán alojadas en nuestro servidor.
- Las imágenes son recortes de los PDF de marca. Para producción hacen falta los originales en alta resolución.

## Confidencialidad

La carpeta `farruquito/` contiene la imagen y la marca de un artista real. No publicarla ni compartirla fuera del equipo hasta que el artista la apruebe.

## Qué trasladar a la aplicación

### Matriz

| Elemento | Valor |
| --- | --- |
| Fondo | `#070707` |
| Texto | `#e8e4de` |
| Acento | `#d4141c` (solo para lo que importa) |
| Titulares | Bebas Neue |
| Voz de marca y citas | Cormorant Garamond cursiva |
| Texto | Archivo |
| Precios y fichas | IBM Plex Mono |

### Tienda Farruquito (tokens de tienda)

| Token | Valor |
| --- | --- |
| Fondo | `#0b0907` |
| Texto | `#efe3cf` (tinta crema) |
| Acento | `#c9a15b` (oro) |
| Titulares | Cinzel |
| Voz | Cormorant Garamond |
| Interfaz | Jost |

### Estructura común

Ambas respetan la estructura fija de la hoja de ruta: cabecera con carrito compartido, portada, catálogo con filtros, ficha de producto (galería, tallas, materiales, plazo de fabricación, devolución) y pie común de Clave de Alma.
