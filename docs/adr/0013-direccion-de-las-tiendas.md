# ADR 0013 · Dirección única de las tiendas: clavedealma.es/<clave>

- Estado: aceptado
- Fecha: 2026-11-04
- Sustituye en parte: ADR 0009 (`STORE_URL_MODE`)

## Contexto

Cada tienda podía verse en dos direcciones, por subdominio (`farruquito.clavedealma.es`) y por carpeta (`clavedealma.es/tienda/farruquito`). `STORE_URL_MODE` elegía cuál se usaba en los enlaces. Había que decidir una sola antes del lanzamiento.

## Decisión

La dirección principal es **`clavedealma.es/<clave>`**, por ejemplo `clavedealma.es/farruquito`.

- **SEO.** Toda la autoridad se queda en un solo dominio, que Google trata como un sitio. Basta con una propiedad en Search Console y un mapa del sitio.
- **Marca.** Es corta y fácil de decir en redes y en directo, y deja claro que la tienda es de Clave de Alma.
- **Técnica.** No necesita DNS ni certificado comodín. La sesión del panel vale en las tiendas sin `SESSION_DOMAIN`, y el carrito y el pago comparten el dominio (fase 4).

`<clave>.clavedealma.es/…` y `/tienda/<clave>/…` redirigen con un **301** a la misma página en la dirección principal, conservando la consulta.

Las tiendas viven en la raíz del dominio, así que la clave no puede coincidir con una página del market. Las palabras reservadas (`Subdomain::RESERVED`) se rechazan en el panel y quedan fuera del patrón de la ruta (`Subdomain::routePattern()`). Por eso `/admin`, `/livewire`, `/carrito`… nunca llegan a una tienda.

## Consecuencias

- Desaparece `STORE_URL_MODE`. `SESSION_DOMAIN` vuelve a `null`.
- Toda página nueva del market cuya ruta tenga un solo tramo (`/carrito`, `/ayuda`…) debe tener su palabra en `RESERVED`.
- La clave no se cambia después de publicar. Si hubiera que hacerlo, la antigua debe redirigir a la nueva.
