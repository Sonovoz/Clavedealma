# ADR 0009 · Tiendas: diseño propio y pasarela común

- **Estado:** aceptada
- **Fecha:** 2026-11-05

## Contexto

Cada artista necesita una tienda con su propia imagen. Pero comprar tiene que funcionar igual en todas, porque el carrito, el pago, la factura y las devoluciones son de Clave de Alma.

Los artículos los gestiona el equipo interno y los artistas no editan nada.

## Decisión

- Toda tienda tiene un **diseño** (`stores.design`) y una **pasarela común**:
  - El diseño es Blade, CSS y JS propios en `storefront/designs/<clave>` y lo hace el equipo de desarrollo.
  - La pasarela es la rejilla, los filtros y la ficha de artículo (`storefront/partials`, `App\Storefront\StoreCatalog`).
  - Si un diseño no tiene una página, se usa la de la plantilla base.
- Los diseños personalizan la pasarela solo mediante variables CSS `--sf-*` y, si quieren, su propia tarjeta. Nunca calculan precios, rebajas ni disponibilidad.
- **Mismas páginas por subdominio y por ruta** (`/tienda/<subdominio>`). `STORE_URL_MODE` decide los enlaces: subdominio en producción, ruta en las pruebas de Hostinger.
- **Vista previa:**
  - Una tienda oculta o en preparación solo la ve el equipo de catálogo con sesión iniciada, con un aviso y `noindex`.
  - Las imágenes propias del diseño se sirven desde `resources/designs` por una ruta que aplica la misma regla.
- **Tipografías alojadas por nosotros** (`@fontsource`, empaquetadas por Vite). Ninguna petición a Google Fonts.

## Consecuencias

- Una tienda nueva se construye sin tocar la pasarela. Los arreglos de la pasarela llegan a todas las tiendas a la vez.
- Las fotos de producto están en el disco público: quien conozca la ruta exacta de una foto puede verla aunque la tienda esté oculta. Pasarlas a un disco privado servido por ruta queda para cuando haya tiendas en preparación con material sensible en producción.
- El carrito («Añadir a la cesta») se activa en la fase 4, con el pago de prueba de Stripe.
