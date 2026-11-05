# Historial del proyecto

Qué se ha ido construyendo, entrega a entrega, de la más reciente a la más antigua. Cada línea es un pull request de GitHub (`Sonovoz/Clavedealma`) y, cuando hubo una decisión técnica, su ADR (pestaña *Decisiones*).

**Regla**: cada entrega nueva añade aquí su línea, en el mismo pull request. Si cambia cómo está hecho algo, se actualiza también la guía (*Cómo está hecho*) y, si toca, el árbol del sistema (`App\Platform\Development\SystemMap`).

## Noviembre de 2026

| Fecha | Entrega | PR | ADR |
| --- | --- | --- | --- |
| 05/11 | **WooCommerce 4, cupones**: *Marketing → Cupones* (porcentaje o fijo, envío gratis, fechas, gasto mínimo, tienda, excluir rebajas, límites), «Aplicar cupón» en el carrito y «¿Tienes un cupón?» al finalizar. El descuento se reparte en las líneas: IVA, factura, comisión y devoluciones cuadran al céntimo. | #32 | 0024 |
| 05/11 | **Portal de artistas**: «Mis ventas» (ventas por producto y día, comisión estimada y comparación con el periodo anterior, sin datos de clientes) y «Avisos» al equipo con fotos o PDF, email y campana del panel. *Tiendas → Ventas por artista / Avisos de artistas* para el superadmin. Pestaña «Historial» en Desarrollo. | #31 | 0023 |
| 05/11 | **Inventario por producto, como WooCommerce**: pestaña «Inventario» (Hay existencias / Agotado, ¿Gestionar inventario?, cantidad, umbral), columna y acciones masivas en el listado. Se quita *Productos → Inventario* y el stock por talla. | #30 | 0022 |
| 05/11 | **WooCommerce 3, «Mi cuenta»**: cuenta opcional del cliente (Escritorio, Pedidos, Ver pedido, Facturas, Devoluciones, Direcciones, Detalles de la cuenta), «¿Crear una cuenta?» al comprar y *Comercio → Clientes*. | #29 | 0021 |
| 05/11 | **WooCommerce 2**: «Finalizar compra» en una sola página, con el pago de Stripe dentro, y «Pedido recibido». | #28 | — |
| 04/11 | **Panel como WooCommerce**: menú (Comercio, Productos, Análisis, Tiendas, Ajustes, Herramientas), estados del pedido con nombres de Woo y «Estado de la tienda» en el Escritorio. Arreglo de las pestañas que daban error 500. | #27 | — |
| 04/11 | **WooCommerce 1**: tienda con «Ordenar por» y paginación, ficha con cantidad, SKU, pestañas y relacionados, y carrito con «Actualizar carrito» y «Calcular envío». | #26 | — |
| 04/11 | **Textos legales** generados por tienda con los datos reales, por versiones y con revisión de la asesoría. | #25 | 0020 |
| 04/11 | **Veri*Factu desactivado** hasta que sea obligatorio; la huella encadenada se sigue guardando. | #24 | 0018 |
| 04/11 | **Desarrollo**: esta guía para Diego, el árbol del sistema y el registro de seguridad. | #23 | 0019 |
| 04/11 | **Fase 6a, facturación**: facturas simplificadas y rectificativas, series correlativas y Veri*Factu preparado. | #22 | 0018 |
| 04/11 | Barra lateral del panel con desplazamiento fino. | #21 | — |
| 04/11 | Pantalla de Fabricación sin barra horizontal. | #20 | — |
| 04/11 | **PDF**: informe de ventas, orden de fabricación, albarán y estado de envíos; página *Informes*. | #19 | 0017 |
| 04/11 | **Fase 5b, devoluciones**: solicitud del cliente, aprobación, recepción y reembolso. | #18 | 0016 |
| 04/11 | **Fase 5a, fabricación y envío**: pasos del pedido, seguimiento y cancelación con devolución del dinero. | #17 | 0015 |
| 04/11 | Botón «Ver tienda» en el panel. | #16 | — |
| 04/11 | **Fase 4, compra**: carrito único, pedidos, pago con Stripe o simulado y webhook. | #15 | 0014 |
| 04/11 | Dirección de cada tienda: `clavedealma.es/<tienda>`. | #14 | 0013 |
| 04/11 | **SEO e IA**: metaetiquetas, canónica, datos estructurados, sitemap, semáforo SEO y asistente de textos. | #13 | 0012 |
| 04/11 | **Copias de seguridad** programadas, con aviso y guía de restauración. | #12 | 0011 |
| 04/11 | Tiendas ocultas visibles para el equipo, en vista previa. | #11 | — |
| 04/11 | **Tienda activa** en el panel y **envíos** por zonas y peso. | #10 | 0010 |
| 04/11 | Matriz de Clave de Alma. | #9 | — |
| 04/11 | **Tienda pública**: diseño propio por tienda y pasarela común. | #8 | 0009 |
| 04/11 | **Ficha de artículo** por pestañas, guía de tallas y precio de referencia de 30 días. | #7 | 0008 |
| 04/11 | **Protocolo de artículos**: importador de hoja + fotos. | #6 | 0007 |
| 04/11 | Farruquito, primera tienda de prueba. | #5 | — |
| 03/11 | Prototipos de diseño. | #4 | — |
| 03/11 | **Fase 2**: módulos de tiendas, catálogo e inventario y sus pantallas. | #3 | 0006 |
| 03/11 | **Fase 1, cimientos**: Laravel + Filament, monolito modular, roles, entornos (Hostinger y Docker) y calidad (pruebas, Pint, Larastan, CI). | #1 | 0001–0005 |
