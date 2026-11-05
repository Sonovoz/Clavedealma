# Clave de Alma · guía para agentes y desarrolladores

Marketplace de ropa de artistas. **Clave de Alma es el vendedor único**: cada artista tiene una tienda interna en `clavedealma.es/<artista>`, pero el cliente compra, paga (Stripe: tarjeta, Bizum, Apple/Google Pay) y recibe la factura de Clave de Alma. Los artistas cobran una comisión mensual mediante autofacturación (Veri*Factu).

Documento de bases y arquitectura completa: ver `docs/README.md`. Cómo está hecho cada parte (también en el panel, *Desarrollo*): `docs/desarrollo/README.md`; mantenlo al día al añadir módulos o flujos.

## Reglas de arquitectura (obligatorias)

- **Monolito modular.** El negocio vive en `app/Modules/<Módulo>/` con subcarpetas `Domain/`, `Application/`, `Infrastructure/`, `Contracts/`, `Database/`.
- Un módulo **no** usa clases internas de otro: solo su `Contracts/` o sus eventos (`Contracts/Events`, ADR 0006). Lo comprueba `tests/Architecture`.
- Cada modelo que aparece en el panel tiene una política con **todas** sus acciones declaradas (`strictAuthorization`).
- `Domain/` no depende de Laravel HTTP, Filament ni proveedores externos.
- La interfaz (`app/Http`, `app/Filament`, `resources/views`) es la capa externa: no contiene reglas de negocio.
- Servicios externos (Stripe, correo, transporte, Veri*Factu) siempre detrás de un contrato propio con driver.
- **Dinero en céntimos enteros** (`int`, `App\Support\Money`) y porcentajes en puntos básicos (`2500` = 25 %), nunca `float` (ADR 0005).
- Los artículos reales entran por la ficha del panel (o, para cargas grandes, por el importador interno `catalogo:importar` del protocolo de `docs/protocolo`), con las mismas reglas (`PublicationCheck`, `SetStockMode`); nunca a mano en el código ni en seeders con datos inventados.
- En el panel el catálogo se trabaja siempre dentro de la **tienda activa** (`ActiveStore`, `ScopedToActiveStore`): productos, colecciones y prendas base son de una tienda (ADR 0010).
- Envíos: Clave de Alma elige el transportista de cada destino; el cliente paga según el baremo por peso (`Shipping\Application\QuoteShipping`). Las tarifas se configuran en el panel, nunca en el código.
- Cada tienda = diseño propio (`storefront/designs/<clave>`) + pasarela común (`storefront/partials`, `App\Storefront\StoreCatalog`). Un diseño nunca calcula precios, rebajas ni disponibilidad (ADR 0009, `docs/tiendas`).
- Dirección de cada tienda: `clavedealma.es/<clave>` (ADR 0013); el subdominio y `/tienda/<clave>` solo redirigen. Toda ruta pública de un tramo (`/carrito`…) reserva su palabra en `Subdomain::RESERVED`.
- Compra (ADR 0014, `docs/compra`): una cesta y un pago para todas las tiendas. Precios, envío e IVA siempre en el servidor (`Cart\Application\PriceCart`); el pago solo por `Payments\Contracts\PaymentGateway` y se da por bueno cuando lo confirma la pasarela. `PAYMENT_DRIVER=fake` nunca en producción. El stock se descuenta al pagar.
- «Mi cuenta» (ADR 0021): cuenta de cliente opcional, de Clave de Alma (no de cada tienda), con las pantallas y nombres de WooCommerce. Un cliente solo ve sus pedidos (`CustomerOrders`); los de invitado, solo con el email confirmado.
- Facturación (ADR 0018, `docs/facturacion.md`): las facturas las emite solo `Invoicing` (al cobrar y al devolver dinero), con numeración correlativa y registro Veri*Factu encadenado; el envío a la AEAT va por `Invoicing\Contracts\VerifactuSender`. Ningún dato fiscal en el código: se rellenan en el panel.
- Seguridad (ADR 0019): toda acción sensible nueva del panel se anota con `App\Platform\Security\SecurityLog::record()`; nunca contraseñas, tokens ni datos de tarjeta.
- Textos legales (ADR 0020): se generan con `App\Platform\Legal\LegalGenerator` a partir de datos reales del sistema (plantillas `resources/views/legal/*`), por versiones inmutables y con revisión de la asesoría. Si cambias una regla que aparece en ellos (devoluciones, envíos, cookies), actualiza la plantilla.
- SEO: toda página pública pasa por `App\Storefront\Seo\SeoBuilder` (título, canónica, JSON-LD) y las reglas de `docs/seo/README.md`; un diseño nunca escribe sus propias metaetiquetas. Fuera de producción no se indexa nada.
- IA: siempre por el contrato `App\Platform\Ai\TextGenerator` (driver en `config/ai.php`); solo propone textos que revisa una persona y nunca recibe datos de clientes.
- El precio tachado de las rebajas lo calcula el sistema con el historial de precios (30 días, ADR 0008): nunca se guarda un «precio anterior» escrito a mano.
- Pedidos, apuntes contables, movimientos de stock y facturas son inmutables: se corrigen con un registro nuevo.
- El mismo código corre en Hostinger (drivers `database`) y en Docker/OVH (Redis, Meilisearch, S3). No uses nada que solo exista en un entorno sin su alternativa.

## Comandos

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed        # crea roles y el superadmin de SUPERADMIN_*
php artisan db:seed --class=DemoSeeder   # datos ficticios para ver el panel
php artisan db:seed --class=FarruquitoSeeder   # primera tienda de prueba (precios de ejemplo)
php artisan catalogo:importar hoja.xlsx --fotos=fotos.zip [--aplicar]   # alta de artículos (docs/protocolo)
composer test                     # pruebas (Pest)
composer lint                     # Pint + Larastan
php artisan copias:crear          # copia de seguridad ahora (se programa en el panel; docs/copias-de-seguridad.md)
bash scripts/build-zip.sh         # ZIP para Hostinger
```

## Convenciones

- Código, clases y tablas en inglés; textos de interfaz, documentación y commits en español.
- Toda decisión técnica relevante se anota en `docs/adr/`.
- Cada cambio entra por rama y pull request; CI debe pasar en verde.
