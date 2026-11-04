# ADR 0020 · Textos legales generados con los datos del sistema

- Estado: aceptado
- Fecha: 2026-11-04

## Decisión

Los textos legales se **generan solos** para Clave de Alma (`/legal/…`) y para cada tienda (`/<tienda>/legal/…`):
- aviso legal;
- política de privacidad;
- política de cookies;
- condiciones de venta (con el formulario de desistimiento);
- envíos y devoluciones.

Se generan al guardar los datos fiscales, al crear una tienda o cambiar su nombre o dirección, y con «Regenerar textos» en *Ajustes → Textos legales*.

- **Solo usan datos reales del sistema** (`App\Platform\Legal\LegalGenerator`, plantillas en `resources/views/legal/*`):
  - los datos fiscales del emisor (*Datos de facturación*);
  - el nombre de cada tienda y su artista;
  - los destinos y tarifas activos, el envío gratis y los plazos;
  - los destinos cerrados;
  - las reglas de devolución (14 días y piezas personalizadas excluidas);
  - el pago con Stripe;
  - las cookies que de verdad usa la web: sesión, XSRF y Stripe en el pago. No hay analítica.
- **Lo que falta** sale como «[pendiente: …]» y se lista en el panel.
- **Versiones**: cada cambio crea una versión nueva. Las versiones no se modifican ni se borran.
- **Lo que acepta el cliente**: cada pedido guarda la versión de las condiciones de venta que aceptó (`condiciones-de-venta vN`).
- **Revisión**: los textos son **una base**. La asesoría legal los revisa y, en el panel, se marcan como «revisados». La marca anota quién y cuándo, y queda en el registro de seguridad. Mientras tanto la web muestra «Borrador pendiente de revisión».
- **Las tiendas** comparten vendedor (Clave de Alma). Sus textos dicen que la tienda oficial de cada artista la opera el titular, y que el artista no es parte del contrato ni trata los datos de los clientes.
- **Si se añaden cookies no técnicas**, por ejemplo analítica, hay que poner un aviso de consentimiento y actualizar la plantilla de cookies.
