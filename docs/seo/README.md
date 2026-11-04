# SEO de Clave de Alma: reglas y estrategia

Esta guía es obligatoria para todo el equipo:

- quien da de alta artículos (gestor de catálogo);
- quien diseña tiendas (desarrollo);
- quien escribe textos o usa la IA.

Nadie puede garantizar el primer puesto en Google, porque cuentan la competencia, los enlaces externos y el tiempo. Lo que sí depende de nosotros es que **cada página sea técnicamente perfecta y tenga el mejor contenido posible**. Esta guía se ocupa de eso.

---

## 1. Lo que hace el sistema solo

Esto ya está programado (`App\Storefront\Seo`) y no hay que tocarlo:

| Qué | Dónde | Regla |
| --- | --- | --- |
| Título y descripción | Todas las páginas públicas | El campo SEO escrito a mano manda. Si está vacío, se genera: `Nombre · Prenda Artista \| Clave de Alma`, sin pasar de 60 caracteres. La descripción se resume a 155 caracteres como máximo. |
| Canónica | Todas | Una sola dirección por página, según `STORE_URL_MODE`. Evita que Google vea duplicadas la versión de subdominio y la de carpeta. |
| No indexar | Tiendas ocultas, borradores y todo el servidor de pruebas | `noindex` + `robots.txt` cerrado fuera de producción (`SEO_INDEXING=false` en Hostinger). |
| Datos estructurados (schema.org) | Matriz: `Organization` + `WebSite`. Tienda y colección: `BreadcrumbList` + `ItemList`. Ficha: `Product` con `Brand`, ofertas por variante con precio real, disponibilidad (`MadeToOrder`, `InStock`, `OutOfStock`) y política de devolución de 14 días, más `BreadcrumbList` | Permite a Google enseñar precio, stock y migas en los resultados. |
| Redes sociales | Todas | Open Graph y Twitter Card con imagen, título y descripción, para que WhatsApp, Instagram y Facebook muestren una tarjeta bonita. |
| Mapa del sitio | `/sitemap.xml` | Matriz, tiendas publicadas, colecciones y artículos publicados, con fecha de cambio y fotos. |
| Robots | `/robots.txt` | En producción: todo abierto salvo `/admin`, más la dirección del mapa. En pruebas: todo cerrado. |
| Idioma | Todas | `lang="es"`, `hreflang="es-ES"` y `og:locale=es_ES`. |
| Velocidad | Todas | Fuentes en nuestro servidor, imágenes con carga diferida, CSS y JS minificados por Vite. |

Lo que **nunca** se hace:
- no hay valoraciones ni estrellas (`AggregateRating`) mientras no existan reseñas reales de clientes. Inventarlas está prohibido por Google y por la ley de consumo;
- tampoco se muestra un «precio anterior» escrito a mano, porque el tachado lo calcula el historial de 30 días (ADR 0008).

---

## 2. Reglas para cada artículo

El **semáforo SEO** de la ficha (pestaña *SEO*) comprueba estas reglas y da una nota de 0 a 100. **Objetivo: 90 o más en todos los artículos publicados.** La nota también aparece como columna en *Productos*.

| Regla | Mínimo | Ideal |
| --- | --- | --- |
| **Título para Google** | Entre 30 y 60 caracteres | Escrito a mano. Empieza por lo que es (prenda + diseño) y termina con el artista: «Camiseta La esencia · Merchandising Farruquito». |
| **Descripción para Google** | Entre 70 y 155 caracteres | Escrita a mano, invita a pulsar: prenda, diseño, que es oficial y un detalle que enamore. |
| **Descripción de la ficha** | Que exista | 80-160 palabras en 2-3 párrafos: qué es, la historia del diseño y el tejido, el corte y los cuidados. |
| **Fotos** | 1 | 3 o más: frontal, espalda y detalle o modelo. 1600 px por el lado largo, en WEBP o JPG ligeros. |
| **Texto alternativo de cada foto** | Que exista y no sea solo el nombre | Lo que se ve: «Camiseta negra La esencia de Farruquito, vista de espalda con el símbolo». |
| **Título único** | — | Dos artículos de la misma tienda nunca comparten título SEO. |
| **Publicado** | — | Un borrador no lo ve Google. |

Además:
- **El nombre del artículo** es el nombre comercial del diseño («La esencia»), no un código.
- **El modelo** (`FQ-OS-01`) es la referencia interna y sale como «Ref.», no en el título.
- **La dirección (slug)** se genera sola y **no se cambia** después de publicar. Si cambia, Google pierde la página.
- **La composición** de la prenda base es obligatoria por ley y ayuda al SEO (Google la lee como `material`).

---

## 3. Reglas para tiendas y colecciones

- **Tienda** (*Tiendas → SEO de la portada*):
  - título con el nombre del artista y «Tienda oficial» o «Merchandising oficial»;
  - descripción con lo que se puede comprar;
  - la frase de portada y la presentación, con texto real del artista. Nunca biografía inventada.
- **Colección** (*Colecciones*): una descripción de 2 o 3 frases sobre la idea de la colección. Es la que posiciona búsquedas como «colección On Stage Farruquito».
- **Diseño de la tienda**:
  - un solo `<h1>` por página (el nombre de la tienda o del artículo);
  - los títulos de las secciones en `<h2>`;
  - las imágenes de ambiente con `alt` vacío si son decorativas;
  - nada de texto importante metido dentro de una imagen.

---

## 4. Estrategia de palabras clave

Cada artista es una marca, y la gente busca **su nombre + lo que quiere comprar**. Por eso:

| Tipo de búsqueda | Ejemplo | Página que debe salir |
| --- | --- | --- |
| Marca + merchandising | «merchandising Farruquito», «tienda oficial Farruquito» | Portada de la tienda |
| Marca + prenda | «camiseta Farruquito», «sudadera Farruquito» | Ficha o colección. Se refuerza con el tipo de prenda en el título |
| Marca + colección o espectáculo | «camiseta Serás Farruquito», «On Stage Farruquito» | Colección |
| Diseño concreto | «camiseta Bailo luego existo» | Ficha (el nombre del diseño va en el título) |
| Genéricas | «regalo flamenco», «merchandising flamenco» | Matriz y portadas (a medio plazo, con contenido) |

Reglas:
- Una página, **una intención de búsqueda principal**. No repetir la misma palabra clave en todos los artículos.
- Escribir para personas: la palabra clave aparece de forma natural en el título, en la descripción y en el primer párrafo, nada más.
- Revisar cada trimestre en Search Console qué búsquedas traen visitas y ajustar títulos.

---

## 5. Enlazado interno

- Matriz → cada tienda publicada (tarjetas de «Tiendas oficiales»).
- Tienda → sus colecciones → sus artículos (pasarela y filtros).
- Ficha → migas de pan hasta tienda y colección.
- Secciones del diseño → artículos concretos por su modelo, como hacen los botones «Comprar» de Farruquito.
- **Nada de páginas huérfanas**: todo artículo publicado está en al menos una colección.

---

## 6. Dirección principal de las tiendas

Para posicionar, **la carpeta (`clavedealma.es/farruquito`) es más fuerte que el subdominio (`farruquito.clavedealma.es`)**: Google suma toda la autoridad en un solo sitio.

- `STORE_URL_MODE=path` hace que la canónica, los enlaces y el mapa usen la carpeta. Es lo recomendado.
- Si se elige el subdominio, cada subdominio debe darse de alta en Search Console. El mapa del sitio de la matriz los lista igualmente.
- **Se decide una vez antes del lanzamiento y no se cambia.** Un cambio posterior obliga a redirigir todas las direcciones.

---

## 7. Asistente de IA

En el panel hay botones «Sugerir con IA» en la ficha (pestaña SEO) y en la tienda. Sirven para:
- título y descripción para Google;
- mejorar la descripción del artículo;
- textos alternativos de las fotos.

Reglas de uso:
1. **La IA propone; una persona revisa y guarda.** Nada se publica solo.
2. **No inventa**: el sistema solo le da los datos del catálogo y le prohíbe añadir datos que no estén (biografía, premios, «edición limitada», envíos gratis…). Aun así, leed siempre lo que propone.
3. **Los textos de las fotos** se generan a partir de los datos, porque la IA no ve las fotos: comprobad que cada texto coincide con su foto.
4. **Nunca se envían datos de clientes** (nombres, emails, direcciones o pedidos) a la IA.
5. **Proveedor**: cualquiera con la API de OpenAI (OpenAI u otros compatibles). Se configura en el `.env`:

   ```text
   AI_DRIVER=openai
   AI_BASE_URL=https://api.openai.com/v1      # o la dirección del proveedor
   AI_API_KEY=…                               # nunca en GitHub ni en el chat
   AI_MODEL=…                                 # el nombre exacto que indique el proveedor
   ```

   Si el proveedor no admite el modo JSON, poned `AI_JSON_MODE=false`. Sin clave, los botones salen desactivados y todo lo demás funciona igual.
6. **Coste**: cada sugerencia cuesta céntimos. Revisad el gasto en el panel del proveedor y poned allí un límite mensual.

---

## 8. Puesta en marcha y seguimiento

Antes del lanzamiento:

1. Decidir `STORE_URL_MODE` (apartado 6) y poner `SEO_INDEXING` sin valor o en `true` **solo en producción**.
2. Dar de alta el dominio en **Google Search Console** y en **Bing Webmaster Tools**, y enviar `https://clavedealma.es/sitemap.xml`.
3. Pasar 3 fichas por la **Prueba de resultados enriquecidos** de Google (datos de producto sin errores).
4. Medir la velocidad con **PageSpeed Insights** (objetivo: LCP < 2,5 s, CLS < 0,1, INP < 200 ms en móvil).
5. Crear el **Perfil de Empresa de Google** de Clave de Alma.

Cada mes:
- Search Console: errores de indexación, búsquedas que traen visitas, páginas con muchas impresiones y pocos clics (hay que mejorar su título o su descripción).
- Panel → *Productos* → columna **SEO**: ningún publicado por debajo de 90.

Cada trimestre:
- Revisar títulos según las búsquedas reales.
- Añadir contenido a colecciones y portadas: historia del espectáculo, lanzamientos, noticias del artista.
- Buscar **enlaces de calidad**: la web oficial del artista, sus redes, notas de prensa, teatros y salas donde actúa. Es lo que más ayuda y no se puede automatizar.

---

## 9. Para desarrolladores

- Metaetiquetas y datos estructurados: `App\Storefront\Seo\SeoBuilder`. Reglas de texto: `SeoText`. Semáforo: `SeoAudit`. IA: `SeoAssistant` sobre el contrato `App\Platform\Ai\TextGenerator`.
- Un diseño de tienda **nunca** escribe sus propias etiquetas `<title>`, `<meta description>` ni JSON-LD: las pone `storefront/partials/seo.blade.php`.
- Toda página pública nueva pasa un `SeoMeta` a la vista. Si no debe indexarse, con `index: false`.
- Pruebas: `tests/Feature/Storefront/SeoTest.php` y `tests/Feature/Platform/AiSeoTest.php`.
