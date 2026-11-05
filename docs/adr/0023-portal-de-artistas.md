# ADR 0023 · Portal de artistas: «Mis ventas» y «Avisos»

- Estado: aceptado
- Fecha: 2026-11-05

## Contexto

Los artistas quieren ver cómo se venden sus piezas sin pedirlo, y contar a Clave de Alma lo que necesitan para su tienda. Por ejemplo, «Firmo camisetas en El Corte Inglés el día 12» o «Nueva camiseta con el logo de temporada».

## Decisión

- **Mismo panel (`/admin`) y mismo acceso.** El artista entra con su usuario (rol Artista), enlazado a su ficha de artista en *Tiendas → Artistas*. Solo ve dos cosas: **Mis ventas** (su escritorio: al entrar va directo ahí) y **Avisos**. El resto del panel le está vedado.
- **Mis ventas** (`App\Filament\Pages\ArtistSalesDashboard`, datos en `App\Platform\Artists\ArtistSales`):
  - Periodos: últimos 7 o 30 días, este mes, mes anterior y este año.
  - Cifras: unidades, ventas sin IVA, **comisión estimada** y pedidos, con su línea de tendencia y la comparación con el periodo anterior.
  - «Ventas por día»: gráfica con el importe y las unidades.
  - «Tus productos»: clasificación con su parte del total, su comisión y su mejor día.
  - «Día a día»: tabla con los días que tuvieron ventas.

  Reglas de los datos:
  - Solo cuentan los pedidos **pagados y reales**: sin compras de prueba ni cancelados.
  - La comisión es la que guarda cada línea del pedido, sobre el precio sin IVA.
  - Las devoluciones se descuentan en la liquidación mensual (fase 6b).
  - **Nunca se enseñan datos de clientes**: solo líneas de producto.
- **El superadministrador** lo ve como cualquier artista (*Tiendas → Ventas por artista*, con selector de artista), para saber qué ve cada uno.
- **Avisos** (`Stores`: `ArtistNotice`, `SendNotice`, `HandleNotice`; eventos `ArtistNoticeSent` y `ArtistNoticeUpdated`):
  - El artista escribe título, mensaje, fecha opcional, producto relacionado y hasta 5 fotos o PDF.
  - Los archivos van al **disco privado**: solo los abren el artista y el superadmin, por `artist-notices.file`.
  - Llegan a los administradores por email (a los superadmin y a `ARTIST_NOTICES_EMAIL`) y por la **campana del panel** (notificaciones de Filament).
  - El equipo los pasa a «En curso» o «Hecho» con una respuesta, y al artista le llega un email (`App\Platform\Artists\SendNoticeEmails`).
  - Cada artista solo ve los suyos (`ArtistNoticePolicy`) y nadie los borra.

## Consecuencias

- Cuando llegue la fase 6b (liquidaciones), «Mis ventas» enlazará con la autofactura de cada mes y restará las devoluciones.
- Si un artista necesita a alguien de su equipo, se crea otro usuario con rol Artista. Hoy cada artista se enlaza con un solo usuario.
