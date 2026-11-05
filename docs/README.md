# Documentación de Clave de Alma

El documento de bases del proyecto (modelo de negocio, decisiones, arquitectura completa, fases) se mantiene en Claude Docs y lo comparten los fundadores. Este directorio recoge lo que necesita quien trabaja con el código.

## Guías

- [**Cómo está hecho (para desarrolladores)**](desarrollo/README.md). También en el panel: *Desarrollo*
- [Instalación de pruebas en Hostinger](instalacion-hostinger.md)
- [Despliegue con Docker en OVH](despliegue-docker.md)
- [Prototipos de diseño: matriz y tienda Farruquito](diseno/README.md)
- [Protocolo de alta de artículos: hoja, fotos e importación](protocolo/README.md)
- [Esquema de tienda: diseño propio y pasarela común](tiendas/README.md)
- [Copias de seguridad: qué se guarda, programación y cómo restaurar](copias-de-seguridad.md)
- [SEO: reglas obligatorias, estrategia y asistente de IA](seo/README.md)
- [Compra y pago: cesta, pedidos, Stripe y pago simulado](compra/README.md)
- [Documentos PDF: fabricación, albaranes e informes](informes.md)
- [Facturación y Veri*Factu](facturacion.md)

## Decisiones técnicas (ADR)

| N.º | Decisión |
| --- | --- |
| [0001](adr/0001-monolito-modular.md) | Monolito modular en Laravel |
| [0002](adr/0002-stripe-pasarela-unica.md) | Stripe como pasarela única |
| [0003](adr/0003-base-de-datos-mariadb.md) | MariaDB/MySQL en todos los entornos |
| [0004](adr/0004-drivers-por-entorno.md) | Mismo código, drivers distintos por entorno |
| [0005](adr/0005-dinero-y-porcentajes-enteros.md) | Dinero en céntimos y porcentajes en puntos básicos |
| [0006](adr/0006-eventos-entre-modulos.md) | Los módulos se comunican con eventos |
| [0007](adr/0007-protocolo-de-articulos.md) | Alta de artículos con hoja y fotos de nombre fijo |
| [0008](adr/0008-ficha-de-articulo.md) | Ficha de artículo, ficha textil y precio de referencia de las rebajas |
| [0009](adr/0009-esquema-de-tienda.md) | Tiendas: diseño propio y pasarela común |
| [0010](adr/0010-tienda-activa-y-envios.md) | Tienda activa en el panel, prendas por tienda y envíos por peso |
| [0011](adr/0011-copias-de-seguridad.md) | Copias de seguridad programables desde el panel |
| [0012](adr/0012-seo-e-ia.md) | SEO técnico en todo el portal e IA por contrato con driver |
| [0013](adr/0013-direccion-de-las-tiendas.md) | Dirección única de las tiendas: `clavedealma.es/<clave>` |
| [0014](adr/0014-compra-pedidos-y-pagos.md) | Compra: cesta única, pedidos inmutables y pago por contrato |
| [0015](adr/0015-fabricacion-envio-y-cancelacion.md) | Fabricación, envío y cancelación con devolución del importe |
| [0016](adr/0016-devoluciones.md) | Devoluciones de piezas (derecho de desistimiento) |
| [0017](adr/0017-documentos-pdf.md) | Documentos PDF del panel (dompdf por contrato) |
| [0018](adr/0018-facturacion-y-verifactu.md) | Facturación (simplificadas y rectificativas) y Veri*Factu |
| [0019](adr/0019-registro-de-seguridad.md) | Registro de seguridad |
| [0020](adr/0020-textos-legales.md) | Textos legales generados con los datos del sistema |
| [0021](adr/0021-mi-cuenta.md) | «Mi cuenta»: cuenta opcional del cliente, como WooCommerce |
| [0022](adr/0022-inventario-por-producto.md) | Inventario por producto, como WooCommerce |
| [0023](adr/0023-portal-de-artistas.md) | Portal de artistas: «Mis ventas» y «Avisos» |
| [0024](adr/0024-cupones.md) | Cupones, como WooCommerce |
| [0025](adr/0025-panel-woo-pedido-y-productos.md) | Panel como WooCommerce: pedido y productos |

## Orden de construcción

1. ✓ Cimientos: proyecto, módulos, roles, panel, entornos, CI.
2. ✓ Tiendas y catálogo en el panel (ficha de artículo, protocolo de importación, gestor de catálogo).
3. ✓ Tiendas públicas: diseño propio + pasarela común (primera: Farruquito).
4. ✓ Compra y pago con Stripe (modo test).
5. ✓ Operación: producción, envíos, devoluciones.
6. **Facturación (Veri*Factu), artistas, liquidaciones y autofacturación** ← fase actual (6a facturas; 6b liquidaciones).
7. Centro de control para superadministradores.
8. Capa visual: plantilla común y temas.
9. Pruebas completas en staging y lanzamiento.

IA: el asistente SEO del panel ya está (ADR 0012, `docs/seo`). Más adelante, sobre el mismo contrato: ofertas, artículo destacado por día o semana y análisis de ventas.

## Módulos

`app/Modules/`: Identity, Stores, Catalog, Inventory, Cart, Orders, Payments, Production, Shipping, Returns, Settlements, Invoicing, Notifications, Audit. Existen ya `Identity`, `Stores`, `Catalog`, `Inventory`, `Shipping`, `Cart`, `Orders`, `Payments`, `Returns` e `Invoicing`; el resto se crea al llegar a su fase, con la misma estructura de carpetas.

## Panel por rol

| Sección | Superadmin | Gestor de catálogo | Fabricante | Artista |
| --- | --- | --- | --- | --- |
| Comercio: pedidos, clientes, devoluciones y facturas (datos de clientes y cobros) | ✓ | — | — | — |
| Comercio → Fabricación y su PDF (sin datos del cliente) | ✓ | — | ✓ | — |
| Productos de la tienda elegida («Tienda: …»): todos los productos, categorías (colecciones), prendas base | ✓ | ✓ | — | — |
| Análisis: informes y PDF de ventas | ✓ | — | — | — |
| Marketing: cupones | ✓ | — | — | — |
| Tiendas: artistas y tiendas (datos fiscales) | ✓ | — | — | — |
| Mis ventas (escritorio del artista): sus ventas por producto y día, sin datos de clientes | ✓ (*Tiendas → Ventas por artista*) | — | — | ✓ |
| Avisos: el artista los envía; el equipo los atiende | ✓ (*Tiendas → Avisos de artistas*) | — | — | ✓ (los suyos) |
| Ajustes: datos de facturación, zonas de envío, transportistas y textos legales | ✓ | — | — | — |
| Herramientas: usuarios, copias de seguridad, registro de seguridad, estado del sistema y Desarrollo | ✓ | — | — | — |

El menú sigue el orden de WooCommerce en WordPress (Escritorio, WooCommerce → Pedidos, Productos, Análisis, Marketing, Ajustes, Herramientas), para que resulte familiar. En el Escritorio, el superadministrador ve «Estado de la tienda»: ventas netas del mes, pedidos procesando, pendientes de pago y devoluciones por revisar. Los estados del pedido se nombran también como en WooCommerce: *Pendiente de pago*, *Procesando* (por fabricar, en fabricación o listo para enviar), *Enviado*, *Completado* y *Cancelado*. *Marketing* aparecerá con los cupones.

- El catálogo se trabaja siempre **dentro de una tienda**, la que se elige arriba en «Tienda: …». Cada tienda tiene sus propias prendas base.
- **Ver tienda** abre la tienda en una pestaña nueva. Está junto al selector, en la franja «Tienda: …», y en el listado y la ficha de *Tiendas*. Cada artículo tiene **Ver en la tienda**, que funciona también con borradores y tiendas ocultas, en vista previa para el equipo.
- El **gestor de catálogo** es equipo interno. Da de alta y edita los artículos de cualquier tienda, pero no ve datos fiscales ni bancarios, ni pedidos o pagos.
- Los **artistas** entran a **Mis ventas** (sus ventas por producto y día) y a **Avisos**, para pedir cambios o contar eventos al equipo (ADR 0023). No ven nada más ni pueden editar su tienda. Su usuario se enlaza en *Tiendas → Artistas*.
- Las cuentas se crean en *Herramientas → Usuarios*.

## Datos de demostración

```bash
php artisan db:seed --class=DemoSeeder
```

Crea 2 artistas con su tienda, 3 prendas base, 6 productos y 28 variantes. No se ejecuta en producción.

```bash
php artisan db:seed --class=FarruquitoSeeder
```

Primera tienda de prueba: Farruquito (oculta), con las colecciones On Stage, Artist Voice e Iconic.

- Tiene 35 piezas, 186 variantes y 60 fotos.
- Los artículos se dan de alta con el [protocolo](protocolo/README.md), a partir de la hoja de ejemplo `database/seeders/data/farruquito/articulos.xlsx` y sus fotos.
- Precios, comisión (25 %) y unidades (25 por variante en On Stage) son **de ejemplo**.
- No se ejecuta en producción.

Si tu base de datos ya tenía la versión anterior de esta tienda, sin referencias de modelo, empieza de cero con `php artisan migrate:fresh --seed` y vuelve a lanzar los dos seeders.
