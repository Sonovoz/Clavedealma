# ADR 0012 · SEO técnico en todo el portal e IA por contrato con driver

- **Estado:** aceptada
- **Fecha:** 2026-11-05

## Contexto

El portal tiene que posicionar en Google cada tienda y cada artículo. Los fundadores quieren además un asistente de IA para mejorar los textos. El proveedor será OpenAI u otro de terceros, no uno fijo.

## Decisión

1. **SEO técnico centralizado** en `App\Storefront\Seo`:
   - `SeoBuilder` genera para cada página pública el título, la descripción, la canónica, la indexación, Open Graph y el JSON-LD de schema.org (`Organization`, `WebSite`, `BreadcrumbList`, `ItemList`, `Product` con ofertas reales).
   - Lo pinta un único parcial, `storefront/partials/seo.blade.php`. Los diseños de tienda no escriben metaetiquetas propias.
2. **Una sola canónica**: `clavedealma.es/<clave>` (ADR 0013).
3. **No indexar** vistas previas, borradores ni ningún entorno que no sea producción (`SEO_INDEXING`). `robots.txt` y `sitemap.xml` se generan en la aplicación.
4. **Campos SEO editables** (`seo_title`, `seo_description`) en artículos, colecciones y tiendas. Si están vacíos, se generan.
   - **Semáforo** `SeoAudit` con reglas fijas, sin IA: longitudes, descripción, fotos, textos alternativos, duplicados y publicación.
5. **IA detrás del contrato** `App\Platform\Ai\TextGenerator`, con drivers:
   - `none` (por defecto): botones desactivados;
   - `openai`: cualquier API con formato `/chat/completions`, configurable por `AI_BASE_URL`, `AI_API_KEY` y `AI_MODEL`.
6. **La IA solo propone**. `SeoAssistant` le envía únicamente datos del catálogo, nunca de clientes, con reglas que le prohíben inventar. Las longitudes se vuelven a comprobar en el código. Una persona revisa y guarda.

## Consecuencias

- Cambiar de proveedor de IA no toca el código. Un proveedor con otra API sería un driver nuevo.
- Las reglas de redacción viven en `docs/seo/README.md` y en `SeoAssistant::RULES`. Si cambian, se actualizan los dos.
- No se publican valoraciones (`AggregateRating`) hasta que existan reseñas reales de clientes.
- Cuando se publique la primera tienda, hay que dar de alta el dominio en Search Console y enviar el mapa del sitio (lista de lanzamiento en `docs/seo`).
