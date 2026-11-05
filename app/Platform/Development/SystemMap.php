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
│   ├── Compra como WooCommerce (un carrito, un pago) ...... checkout/* · CartController · CheckoutController
│   │   ├── Carrito ........................................... Cart (CartStore, PriceCart)
│   │   ├── Finalizar compra: datos, envío y pago ............. Shipping (ShippingQuoter) · Payments (Redsys / simulado)
│   │   ├── Pedido recibido · seguimiento · devolución · facturas  Orders · Returns · Invoicing
│   │   └── Mi cuenta (opcional): pedidos, facturas, direcciones . Identity (RegisterCustomer) · CustomerOrders
│   └── SEO: metaetiquetas, JSON-LD, sitemap.xml, robots.txt  SeoBuilder · SitemapController
│
├── Panel /admin (Filament, por roles)
│   ├── Escritorio .... Estado de la tienda (ventas del mes, procesando…) ......... superadmin
│   ├── Comercio ...... Pedidos · Clientes · Devoluciones · Facturas ................ superadmin
│   │   └── Fabricación (sin datos del cliente) .................................. superadmin + fabricante
│   ├── Productos ..... Todos los productos · Categorías · Prendas base (tienda activa)  superadmin + gestor
│   ├── Análisis ...... Informes ...................................................... superadmin
│   ├── Marketing ..... Cupones ....................................................... superadmin
│   ├── Tiendas ....... Artistas · Tiendas · Ventas por artista · Avisos de artistas  superadmin
│   ├── Mis ventas · Avisos (escritorio del artista, sin datos de clientes) ........ artista
│   ├── Ajustes ....... Datos de facturación · Zonas de envío · Transportistas · Textos legales
│   └── Herramientas .. Usuarios · Copias · Registro de seguridad · Estado del sistema · Desarrollo
│
├── Negocio (app/Modules, solo se hablan por Contracts y eventos)
│   ├── Identity ..... usuarios, roles y cuentas de cliente
│   ├── Stores ....... artistas, tiendas y avisos ──────── StoreDirectory · ArtistNoticeSent
│   ├── Catalog ...... prendas, productos, variantes, precios ─ VariantLookup · VariantCreated
│   ├── Inventory .... inventario por producto ─────────── StockAvailability · StockShortage
│   ├── Shipping ..... zonas, baremo, transportistas ───── ShippingQuoter · CarrierDirectory
│   ├── Cart ......... cesta calculada en el servidor ──── PricedCart
│   ├── Promotions ... cupones y reparto del descuento ─── Coupons · CouponResult
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
    ├── Redsys ............ PaymentGateway (tarjeta y Bizum; Stripe, apagado)
    ├── IA (OpenAI o compatible) ... TextGenerator
    ├── AEAT Veri*Factu ... VerifactuSender (pendiente del certificado)
    ├── Correo (SMTP) ..... Laravel Mail
    └── Almacenamiento .... disco local (Hostinger) o S3 (OVH)

Flujo de una venta
  Añadir → Cesta (PriceCart) → PlaceOrder (pendiente) → StartPayment → Redsys
    → HandleGatewayEvent → PaymentSucceeded → MarkOrderPaid → OrderPaid
        ├── Inventory: RecordSale (descuenta inventario / StockShortage)
        ├── Orders: emails al cliente y al equipo
        └── Invoicing: factura simplificada + registro Veri*Factu
  → Fabricación → Listo → Enviado (OrderShipped: email) → Entregado
  → (Devolución o cancelación) → Refunds → RefundIssued → factura rectificativa
TXT;
}
