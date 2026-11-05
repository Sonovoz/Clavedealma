# Cómo está hecho Clave de Alma

Hola, Diego. Esta guía explica cómo se ha construido cada parte del proyecto, dónde vive y qué reglas hay que respetar para que siga creciendo sin romperse. En el panel la tienes en **Desarrollo**, junto con el árbol del sistema, que se genera solo a partir del código. Las decisiones con su porqué están en los ADR (`docs/adr`, pestaña *Decisiones*), y lo que se ha ido entregando, en la pestaña *Historial* (`docs/desarrollo/historial.md`).

---

## 1. El negocio en cinco líneas

- **Clave de Alma es el único vendedor.** Cada artista tiene su tienda (`clavedealma.es/<artista>`), pero el cliente compra, paga y recibe la factura de Clave de Alma.
- Hay **una sola cesta y un solo pago** para todas las tiendas.
- Las piezas se **fabrican bajo pedido**. Un producto se puede marcar «Agotado» o limitar a una cantidad (ADR 0022).
- Clave de Alma elige el transportista y cobra el envío por peso.
- Cada mes el artista cobra una comisión, por autofacturación (fase 6b).

## 2. Tecnología y entornos

| Pieza | Qué se usa |
| --- | --- |
| Lenguaje y framework | PHP 8.3, **Laravel 13** |
| Panel de gestión (`/admin`) | **Filament 5** (Livewire) |
| Tiendas públicas | Blade + CSS/JS propios por diseño, compilados con **Vite**. Fuentes alojadas en el servidor (`@fontsource`) |
| Base de datos | MariaDB/MySQL en servidores; SQLite en local y en las pruebas |
| Pagos | **Stripe** (Payment Element + webhook) y pago simulado para pruebas |
| PDF | **dompdf**, por contrato propio |
| QR de Veri*Factu | `bacon/bacon-qr-code` (SVG) |
| Pruebas y calidad | **Pest 4**, Pint, **Larastan nivel 6** y pruebas de arquitectura |
| Entornos | **Hostinger** (pruebas, drivers `database`) y **Docker en OVH** (producción: Redis, Meilisearch, S3). El mismo código en los dos (ADR 0004) |

## 3. Arrancar en local

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed                      # roles + superadmin de SUPERADMIN_*
php artisan db:seed --class=FarruquitoSeeder    # tienda de prueba (precios de ejemplo)
npm run build                                   # o npm run dev
composer test && composer lint                  # siempre en verde antes de subir
```

En local el pago es **simulado** (`PAYMENT_DRIVER=fake`) y los emails van al log (`MAIL_MAILER=log`).

## 4. La regla de oro: monolito modular

Todo el **negocio** vive en `app/Modules/<Módulo>/`, y cada módulo tiene siempre las mismas carpetas:

| Carpeta | Qué va dentro | Qué **no** puede hacer |
| --- | --- | --- |
| `Contracts/` | Lo único que otros módulos pueden usar: interfaces, DTO de solo lectura, enums y `Contracts/Events` | Depender de su propia `Infrastructure` |
| `Domain/` | Reglas puras: cálculos, estados y políticas. Se prueba sin base de datos | Usar HTTP, Filament, Livewire o Stripe |
| `Application/` | Casos de uso: lo que hace el sistema (`PlaceOrder`, `IssueRefund`…) | — |
| `Infrastructure/` | Modelos Eloquent, listeners, drivers, comandos y emails | — |
| `Database/` | Migraciones del módulo, que carga su `ServiceProvider` | — |

**Un módulo nunca usa las clases internas de otro.** Solo puede usar sus `Contracts` o escuchar sus eventos (ADR 0001 y 0006). Lo comprueba `tests/Architecture/ModulesTest.php` para **cada par de módulos**: si alguien se lo salta, las pruebas fallan.

Fuera de los módulos está la **capa exterior**, que pinta y conecta pero no decide reglas de negocio:

| Carpeta | Qué es |
| --- | --- |
| `app/Http` | Controladores de la tienda y de la compra, y `FormRequest` |
| `app/Filament` | El panel: recursos, páginas, acciones y la tienda activa |
| `app/Storefront` | Lectura del catálogo para las tiendas, SEO, direcciones y cesta para pintar |
| `app/Platform` | Servicios transversales: IA, copias, PDF, documentos, importador, ajustes, textos legales y registro de seguridad |
| `app/Support` | Utilidades sin negocio: `Money` y `HasLabel` |
| `resources/views` | Blade: `storefront/` (tiendas), `checkout/` (compra), `pdf/`, `mail/` y `filament/` |

## 5. Los módulos, uno a uno

| Módulo | Qué hace | Piezas clave | Lo que ofrece a los demás |
| --- | --- | --- | --- |
| **Identity** | Usuarios y roles: superadmin, gestor de catálogo, fabricante, artista y cliente | `User`, `Role`; `Gate::before` da todo al superadmin | `Role` |
| **Stores** | Artistas y tiendas: diseño, comisión y estado | `Store`, `Artist`, `Subdomain` (clave de la dirección y palabras reservadas) | `StoreDirectory` → `StoreInfo`, `StoreStatus` |
| **Catalog** | Prendas base, productos, variantes, fotos, colecciones e historial de precios | `PublicationCheck`, `GenerateVariants`, `ReferencePrice` (rebaja legal a 30 días), `SizeScale` | `VariantLookup` → `SellableVariant`, evento `VariantCreated` |
| **Promotions** | Cupones como WooCommerce: reglas, reparto del descuento por línea y usos | `CouponPolicy`, `DiscountAllocator`, `RecordRedemption`, `ReleaseRedemption` | `Coupons::evaluate`, `CouponResult`, `CouponLine` |
| **Inventory** | Inventario por producto (estado, cantidad opcional) y movimientos inmutables | `StockPolicy`, `ConfigureStock`, `AdjustStock`, `RecordSale`, `RestockCancelledSale`, `RestockReturn` | `StockAvailability::productLimits`, `StockStatus`, evento `StockShortage` |
| **Shipping** | Transportistas, destinos y baremo por peso | `ZoneResolver` (país + CP → zona), `WeightScale`, `QuoteShipping` | `ShippingQuoter`, `CarrierDirectory`, `ShippingZone` |
| **Cart** | Cesta única en la sesión (variante → unidades) | `CartStore`, `PriceCart` (recalcula precio, stock y tienda) | `PricedCart`, `PricedLine` |
| **Orders** | Pedido inmutable, sus pasos e historial | `PlaceOrder`, `MarkOrderPaid`, `AdvanceOrder`, `CancelPaidOrder`, `OrderFlow` | `OrderLookup`, `OrderStatus`, eventos `OrderPaid`, `OrderShipped`, `OrderCancelled`, `PaidOrderCancelled` |
| **Payments** | Pasarela, cobros, avisos y devoluciones de dinero | `StartPayment`, `HandleGatewayEvent` (idempotente), `IssueRefund`; drivers `StripeGateway` y `FakeGateway` | `PaymentGateway`, `Refunds`, eventos `PaymentSucceeded`, `PaymentFailed`, `RefundIssued` |
| **Returns** | Devoluciones de piezas (14 días) | `ReturnPolicy`, `RequestReturn`, `ProcessReturn` | evento `ReturnReceived` |
| **Invoicing** | Facturas simplificadas y rectificativas, y Veri*Factu | `InvoiceWriter` (numeración + huella), `IssueInvoices`, `Verifactu`, `VatSplit` | `VerifactuSender`, `InvoiceType`, `AeatStatus` |

## 6. Cómo se hablan: los flujos de punta a punta

### Compra y pago
1. **Ficha de producto**: «Añadir» llama a `POST /carrito`. `PriceCart` comprueba que la pieza está a la venta, que hay stock y que la tienda está visible. `CartStore` guarda solo variante y unidades.
2. **`/finalizar-compra`** (una sola página, como WooCommerce): el cliente escribe su dirección y `ShippingQuoter` calcula la zona y el precio por peso. Al pulsar «Realizar el pedido» pasa esto:
   - `PlaceOrder` crea el pedido **pendiente de pago**, con una copia de cada línea (precio, IVA, comisión y peso).
   - `StartPayment` crea el cobro con una clave de idempotencia por pedido e importe.
   - El Payment Element de Stripe, ya montado en la página («deferred intent»), lo confirma sin salir de ella.
   - Si el cliente repite con los mismos datos, se reutiliza el pedido. Si los cambia, `CancelPendingOrder` anula el anterior y su cobro.
   - Con sesión (o con «¿Crear una cuenta?», que llama a `Identity\Application\RegisterCustomer`), el pedido guarda `user_id` y la dirección pasa a la cuenta (`CustomerAddress`).
3. **`/pedido/<token>/pagar`** («Pagar el pedido»): la misma confirmación para un pedido ya creado. Se usa sin JavaScript o al volver de un pago fallido.
4. **Stripe confirma** por el webhook firmado o por la consulta al volver. `HandleGatewayEvent` registra el aviso una sola vez y lanza `PaymentSucceeded`.
5. Con ese evento, **Orders** marca el pedido pagado (`MarkOrderPaid`) y lanza `OrderPaid`. Lo escuchan:
   - **Inventory**, que descuenta el stock (`RecordSale`) o avisa de un faltante;
   - **Orders**, que envía los emails;
   - **Invoicing**, que emite la factura.

### Después del pago
`OrderFlow` define los pasos: pagado → en fabricación → listo → enviado → entregado.
- El **fabricante** trabaja en *Comercio → Fabricación*. Esa pantalla no muestra datos del cliente.
- El **superadmin** envía el pedido con su seguimiento (`OrderShipped` → email).
- **Cancelar un pedido pagado** devuelve el dinero primero con `Refunds`. Después `PaidOrderCancelled` repone el stock y avisa al cliente, y `RefundIssued` emite la rectificativa.

### Devolución
1. El cliente la pide desde su pedido con `RequestReturn`. El importe queda fijado: las piezas, más el envío si vuelve todo.
2. El equipo la aprueba y, al recibir el paquete, `ProcessReturn` hace tres cosas:
   - lanza `ReturnReceived`, con el que Inventory repone las piezas;
   - devuelve el dinero con `Refunds`;
   - con eso salta `RefundIssued` y se emite la rectificativa.

### Alta de artículos
- Se dan de alta en la **ficha del panel**, dentro de la **tienda activa** (`ActiveStore`, `ScopedToActiveStore`).
- Para cargas grandes está `php artisan catalogo:importar` (`docs/protocolo`).
- `PublicationCheck` decide si un artículo puede publicarse.
- Los precios tachados salen del historial de precios: **nunca** se escriben a mano.

## 7. Datos: tres reglas que no se negocian

1. **Dinero en céntimos enteros** (`int`, `App\Support\Money`) y porcentajes en puntos básicos (`2100` = 21 %). Nunca `float` (ADR 0005).
2. **Inmutables**: pedidos, líneas, movimientos de stock, devoluciones, facturas y el registro de seguridad. El modelo lanza `LogicException` si alguien intenta editarlos o borrarlos, y los errores se corrigen con un registro nuevo.
3. **Ningún dato real en el código**: ni precios, ni datos fiscales, ni tarifas. Todo eso se rellena en el panel. Los seeders son de demostración.

## 8. La tienda pública

- **Rutas**:
  - `clavedealma.es/<clave>` es la tienda (ADR 0013);
  - el subdominio y `/tienda/<clave>` redirigen con un 301;
  - toda página pública de un solo tramo (`/carrito`…) **reserva su palabra** en `Subdomain::RESERVED`.
- **Diseño propio + pasarela común** (ADR 0009):
  - cada tienda tiene su diseño en `resources/views/storefront/designs/<clave>`;
  - la pasarela de productos (`storefront/partials`, `StoreCatalog`) es la misma para todas;
  - un diseño **nunca** calcula precios ni disponibilidad.
- **SEO** (ADR 0012): toda página pública pasa por `SeoBuilder` (título, canónica, JSON-LD). Fuera de producción no se indexa nada.
- **Compra** (`resources/views/checkout`): imagen de Clave de Alma, común a todas las tiendas, siempre `noindex`.

## Textos legales

`App\Platform\Legal\LegalGenerator` genera el aviso legal, la privacidad, las cookies, las condiciones y los envíos de Clave de Alma y de cada tienda. Usa solo datos del sistema y las plantillas Markdown de `resources/views/legal/*`. Cada cambio es una versión nueva (`LegalDocument`, inmutable) y la asesoría la marca como revisada (ADR 0020). Si cambias una regla de negocio que aparece en los textos (plazos, devoluciones, cookies…), **actualiza la plantilla**.

## 9. El panel

- **`strictAuthorization`**: cada modelo del panel tiene una política con **todas** sus acciones declaradas. Para lo que solo puede tocar el superadmin está `SuperadminOnlyPolicy`.
- **Roles**:
  - el gestor de catálogo no ve clientes ni cobros;
  - el fabricante solo ve Fabricación e Inventario;
  - el artista solo ve **Mis ventas** y **Avisos** (ADR 0023). Sus datos salen de `App\Platform\Artists\ArtistSales` (solo líneas de pedidos pagados y reales, nunca clientes) y `ArtistScope` decide qué artista ve cada uno.
- Las **acciones con reglas** (enviar, cancelar, devolver) llaman a los casos de uso del módulo (`OrderActions`, `ProcessReturn`). El panel nunca escribe en las tablas por su cuenta.
- **Menú como WooCommerce**: los grupos se declaran en `AdminPanelProvider` (Comercio, Productos, Análisis, Marketing, Tiendas, Ajustes, Herramientas), y cada recurso o página fija su `$navigationGroup` y su `$navigationSort`. Una pantalla nueva va al grupo donde estaría en WooCommerce. Los nombres son también los de WooCommerce: *Todos los productos*, *Categorías* (son las colecciones), *Inventario*, *Zonas de envío*, *Estado del sistema*. El recuadro «Estado de la tienda» del Escritorio es `App\Filament\Widgets\StoreStatusWidget`.
- **«Mi cuenta»** (ADR 0021) vive en `app/Http/Controllers/Account` y `resources/views/account`. Para saber qué pedidos son de un cliente se usa siempre `App\Storefront\Account\CustomerOrders`: los suyos y, con el email confirmado, los de invitado con ese email. En el panel, `CustomerResource` (*Comercio → Clientes*).
- **Estados del pedido**: en el panel se leen como en WooCommerce (`OrderStatus::label()`). *Procesando* agrupa los pagados sin enviar (`OrderStatus::processing()`), y *Completado* es el entregado. El valor guardado no cambia.
- **Ficha del pedido** (ADR 0025): «Notas del pedido» lee `order_events`. «Añadir nota» llama a `Orders\Application\AddOrderNote`: la privada solo la ve el equipo, y la del cliente le llega por email (`OrderNoteMail`) y sale en «Actualizaciones del pedido» (`OrderPage`). «Acciones del pedido» llama a `ResendOrderEmails`. Cada apunte guarda su autor (`author`).
- **Los PDF** se generan en `App\Platform\Documents\*`, con las plantillas en `resources/views/pdf/*`.

## 10. Servicios externos, siempre por contrato

| Servicio | Contrato | Drivers |
| --- | --- | --- |
| Pagos | `Payments\Contracts\PaymentGateway` | `stripe`, `fake` (nunca en producción) |
| IA (SEO) | `App\Platform\Ai\TextGenerator` | `openai` (compatible), `null` |
| PDF | `App\Platform\Pdf\PdfRenderer` | dompdf |
| Veri*Factu | `Invoicing\Contracts\VerifactuSender` | `none` (hoy); `aeat` cuando haya certificado |

Para añadir un proveedor se escribe otro driver del mismo contrato y se registra en el `ServiceProvider`. El resto del código no se toca.

## 11. Seguridad

- **Secretos** solo en el `.env` del servidor: claves de Stripe, IA, SMTP y certificados. **Nunca** en GitHub ni en el chat.
- **Webhooks**: solo con firma válida, y cada aviso se aplica una vez.
- **Pago**: la tarjeta no pasa por nuestro servidor (PCI SAQ A).
- **Enlaces privados de cliente**: `/pedido/<token>` con 48 caracteres aleatorios.
- **Datos personales**: el fabricante no los ve y la IA nunca los recibe.
- **Registro de seguridad** (*Herramientas → Registro de seguridad*):
  - anota accesos, intentos fallidos, cambios de usuarios y roles, devoluciones de dinero, cancelaciones, datos fiscales y copias;
  - no se puede modificar y se purga a los 12 meses (`seguridad:purgar`).

## 12. Cómo trabajar

- **Cada cambio va en su rama y su pull request**, con la CI en verde (`.github/workflows/ci.yml`: Pest, Pint y Larastan sobre MariaDB).
- **Idioma**: el código, las clases y las tablas en inglés; los textos de la interfaz, la documentación y los commits en español.
- **Decisiones**: toda decisión técnica relevante se anota en un ADR nuevo en `docs/adr/`.
- **Historial**: **cada entrega añade su línea** en `docs/desarrollo/historial.md` (pestaña *Historial*), en el mismo pull request. Si cambia cómo está hecho algo, se actualiza esta guía y, si toca, el árbol del sistema (`SystemMap`).
- **Módulo nuevo**:
  1. Crea `app/Modules/X/{Contracts,Domain,Application,Infrastructure,Database}` y su `XServiceProvider`.
  2. Regístralo en `bootstrap/providers.php`.
  3. Las pruebas de arquitectura lo vigilan solas.
- **Modelo nuevo en el panel**: dale su política y regístrala en `AppServiceProvider`.
- **Instrucciones para agentes de IA**: están en `CLAUDE.md` (igual que `AGENTS.md`).

## 13. Dónde está cada guía

| Tema | Guía |
| --- | --- |
| Bases y orden de fases | `docs/README.md` |
| Instalación de pruebas en Hostinger | `docs/instalacion-hostinger.md` |
| Producción con Docker en OVH | `docs/despliegue-docker.md` |
| Alta de artículos (hoja + fotos) | `docs/protocolo/README.md` |
| Esquema de tienda | `docs/tiendas/README.md` |
| SEO e IA | `docs/seo/README.md` |
| Compra, pago, pedidos y devoluciones | `docs/compra/README.md` |
| PDF e informes | `docs/informes.md` |
| Facturación y Veri*Factu | `docs/facturacion.md` |
| Copias de seguridad | `docs/copias-de-seguridad.md` |
| Historial de entregas | `docs/desarrollo/historial.md` |

## 14. Lo siguiente

- **Fase 6b, liquidaciones a artistas**: autofactura mensual con retención, enlazada desde «Mis ventas» (que ya existe, ADR 0023).
- **Driver `aeat` de Veri*Factu**, en cuanto esté el certificado.
- **Fase 7**: centro de control para superadministradores.
- **Antes del lanzamiento**:
  - textos legales;
  - datos fiscales;
  - tarifas reales;
  - claves de Stripe en modo real;
  - SMTP;
  - `SEO_INDEXING` solo en producción.
