# Esquema de tienda

Todas las tiendas de artistas siguen el mismo esquema: **un diseño propio y una pasarela de productos común**.

| Pieza | Qué es | Quién la hace | Dónde vive |
| --- | --- | --- | --- |
| **Diseño** | Portada, secciones, ambiente de cada colección, cabecera y pie con la imagen del artista. | El equipo de desarrollo, a partir de las fotos y estilos del artista. | `resources/views/storefront/designs/<clave>/`, `resources/css/storefront/designs/<clave>.css`, `resources/js/storefront/designs/<clave>.js` e imágenes en `resources/designs/<clave>/`. |
| **Pasarela** | Colecciones, filtros, rejilla de artículos y ficha del artículo: tallas, colores, guía de tallas, rebaja legal, composición y cuidados. | Nadie la toca. Muestra sola lo que el catálogo asigna a la tienda. | `resources/views/storefront/partials/` y `app/Storefront/StoreCatalog.php`. |
| **Artículos** | Los productos de la tienda. | El gestor de catálogo, desde la ficha del panel o la hoja del [protocolo](../protocolo/README.md). | Panel: *Catálogo*. |

El artista **no edita nada**. Solo recibe información de sus ventas.

## Direcciones

La dirección principal de cada tienda es **`https://clavedealma.es/<clave>`**, por ejemplo `clavedealma.es/farruquito` (ADR 0013). Es la única que usan los enlaces, la canónica y el mapa del sitio.

Además, dos direcciones redirigen ahí de forma permanente (301), conservando la página:
- `https://<clave>.clavedealma.es`, para quien la escriba así. Solo funciona si el DNS tiene el comodín `*.clavedealma.es`, que es opcional.
- `https://clavedealma.es/tienda/<clave>`, la dirección antigua de las pruebas.

La clave se elige en *Tiendas → Dirección de la tienda* y **no se cambia después de publicar**. No pueden usarse las palabras reservadas del market (`admin`, `carrito`, `pedidos`, `ayuda`, `legal`…; lista en `App\Modules\Stores\Domain\Subdomain::RESERVED`).

Páginas de cada tienda:

| Página | Ruta |
| --- | --- |
| Portada (diseño + pasarela) | `/<clave>` |
| Colección | `/<clave>/coleccion/<colección>` |
| Ficha de artículo | `/<clave>/p/<artículo>` |

La portada del market (`/`) es la **matriz de Clave de Alma**, con el diseño de [`docs/diseno/matriz`](../diseno/matriz/index.html). Su sección «Tiendas oficiales» se rellena sola:

- Muestra las tiendas publicadas. El equipo, con la sesión iniciada, ve también las ocultas, marcadas «solo equipo».
- La foto de cada tarjeta es `resources/designs/<diseño>/tarjeta.webp`, si existe.
- Los estilos para el filtro se eligen en el panel: *Tiendas → Estilos musicales*.
- Del prototipo se ha quitado el drop de productos inventados y su carrito.
- Los formularios del Club y de la comunidad todavía no guardan datos.

## Visibilidad

| Estado de la tienda | Quién la ve |
| --- | --- |
| Publicada | Todo el mundo. |
| Oculta / En preparación | Solo el superadministrador y el gestor de catálogo con sesión iniciada. Ven una franja amarilla de «vista previa», y la página no se indexa. |

Las imágenes del diseño (retratos, logotipos…) se sirven por `/_diseno/<clave>/<fichero>` y siguen la misma regla: las de una tienda oculta no salen a Internet.

Los artículos en borrador no aparecen en la pasarela. El equipo puede abrir su ficha para revisarla antes de publicar.

## Crear el diseño de una tienda nueva

1. Dar de alta el artista y la tienda en el panel, con la tienda en estado *Oculta*.
2. Crear `resources/views/storefront/designs/<clave>/`. Es obligatorio:
   - `layout.blade.php` (cabecera, `@yield('content')` y pie), que extiende `storefront.layouts.shop`.

   Es opcional, y si falta se usa la versión de la plantilla base:
   - `home.blade.php`: secciones propias + `@include('storefront.partials.showcase')`.
   - `collection.blade.php`, `product.blade.php`: incluyen la pasarela o la ficha comunes.
   - `card.blade.php`: tarjeta con el ambiente del artista. Usa los mismos datos y los atributos `data-card`, `data-collections` y `data-type`.
3. Crear la hoja de estilos del diseño:
   - Importa `../showcase.css`.
   - Define las variables `--sf-*`: fondo, tinta, acento, líneas, fuentes y color de rebaja.
   - Carga las fuentes con `@fontsource`. Nunca desde Google Fonts.
4. El JS del diseño importa `../app.js` (filtros y ficha) y añade sus efectos.
5. Registrar el diseño en `config/storefront.php` y sus entradas en `vite.config.js`.
6. En el panel, *Tiendas → Diseño de la tienda*, elegir el diseño nuevo.
7. Cargar los artículos (ficha o hoja). Cuando el artista lo apruebe, pasar la tienda a *Publicada*.

**Reglas del diseño:**

- Puede enlazar a artículos concretos por su modelo (en la portada, `$p('FQ-OS-01')` lleva a la ficha).
- No calcula precios, rebajas ni disponibilidad: los recibe de la pasarela.
- No guarda datos de producto en el código. Si una sección necesita un artículo, lo busca por su modelo, y si ese artículo no está publicado, el enlace va a la pasarela.

## Diseños disponibles

| Clave | Tienda | Origen |
| --- | --- | --- |
| `base` | Plantilla neutra para tiendas sin diseño propio. | — |
| `farruquito` | Farruquito: On Stage, Artist Voice e Iconic. | Prototipo [`docs/diseno/farruquito`](../diseno/farruquito/index.html), confidencial. |
