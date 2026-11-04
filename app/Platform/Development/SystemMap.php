<?php

namespace App\Platform\Development;

/**
 * Esquema conceptual del sistema: quién usa qué y cómo viajan los datos.
 * Se mantiene a mano junto a docs/desarrollo/README.md.
 */
final class SystemMap
{
    public const TREE = <<<'TXT'
Clave de Alma (vendedor único)
├── Público
│   ├── Matriz · clavedealma.es ............................ MarketController · storefront/market
│   ├── Tiendas · clavedealma.es/<artista> ................. StorefrontController
│   │   ├── Diseño propio ................................. storefront/designs/<clave>
│   │   └── Pasarela común ................................ StoreCatalog · storefront/partials
│   ├── Compra (una cesta, un pago) ........................ checkout/* · CartController · CheckoutController
│   │   ├── Cesta ............................................. Cart (CartStore, PriceCart)
│   │   ├── Datos y envío ..................................... Shipping (ShippingQuoter)
│   │   ├── Pago .............................................. Payments (Stripe / simulado)
│   │   └── Mi pedido · seguimiento · devolución · facturas ... Orders · Returns · Invoicing
│   └── SEO: metaetiquetas, JSON-LD, sitemap.xml, robots.txt  SeoBuilder · SitemapController
│
├── Panel /admin (Filament, por roles)
│   ├── Ventas ........ Pedidos · Devoluciones · Facturas · Informes ............ superadmin
│   ├── Tiendas ....... Artistas · Tiendas ........................................ superadmin
│   ├── Catálogo ...... Productos · Colecciones · Prendas base (tienda activa) ..... superadmin + gestor
│   ├── Operación ..... Fabricación (sin datos del cliente) · Stock ............... superadmin + fabricante
│   ├── Envíos ........ Tarifas por destino · Transportistas ........................ superadmin
│   ├── Administración  Usuarios · Sistema · Copias · Datos de facturación · Registro de seguridad
│   └── Desarrollo .... esta guía, el árbol y las decisiones ....................... superadmin
│
├── Negocio (app/Modules, solo se hablan por Contracts y eventos)
│   ├── Identity ..... usuarios y roles
│   ├── Stores ....... artistas y tiendas ─────────────── StoreDirectory
│   ├── Catalog ...... prendas, productos, variantes, precios ─ VariantLookup · VariantCreated
│   ├── Inventory .... stock y movimientos ─────────────── StockAvailability · StockShortage
│   ├── Shipping ..... zonas, baremo, transportistas ───── ShippingQuoter · CarrierDirectory
│   ├── Cart ......... cesta calculada en el servidor ──── PricedCart
│   ├── Orders ....... pedido inmutable y sus pasos ────── OrderLookup · OrderPaid · OrderShipped · PaidOrderCancelled
│   ├── Payments ..... cobros, avisos y devoluciones ───── PaymentGateway · Refunds · PaymentSucceeded · RefundIssued
│   ├── Returns ...... devoluciones de piezas ──────────── ReturnReceived
│   └── Invoicing .... facturas y Veri*Factu ───────────── VerifactuSender
│
├── Plataforma (app/Platform, transversal)
│   ├── IA (TextGenerator) · PDF (PdfRenderer) · Documentos · Importador de catálogo
│   ├── Copias de seguridad (programadas) · Ajustes · Estado del sistema
│   └── Registro de seguridad (accesos y acciones sensibles)
│
└── Servicios externos (siempre por contrato y driver)
    ├── Stripe ............ PaymentGateway
    ├── IA (OpenAI o compatible) ... TextGenerator
    ├── AEAT Veri*Factu ... VerifactuSender (pendiente del certificado)
    ├── Correo (SMTP) ..... Laravel Mail
    └── Almacenamiento .... disco local (Hostinger) o S3 (OVH)

Flujo de una venta
  Añadir → Cesta (PriceCart) → PlaceOrder (pendiente) → StartPayment → Stripe
    → HandleGatewayEvent → PaymentSucceeded → MarkOrderPaid → OrderPaid
        ├── Inventory: RecordSale (descuenta stock / StockShortage)
        ├── Orders: emails al cliente y al equipo
        └── Invoicing: factura simplificada + registro Veri*Factu
  → Fabricación → Listo → Enviado (OrderShipped: email) → Entregado
  → (Devolución o cancelación) → Refunds → RefundIssued → factura rectificativa
TXT;
}
