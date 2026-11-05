# ADR 0025 · Panel como WooCommerce: pedido y productos

- Estado: aceptado
- Fecha: 2026-11-05

## Contexto

Quien lleva la tienda viene de muchos años de WooCommerce. La primera parte (#27) ordenó el menú y los estados del pedido. Faltaban las dos pantallas en las que más tiempo se pasa: la ficha del pedido y el listado de productos.

## Decisión

- **Ficha del pedido**:
  - **«Notas del pedido»** sustituye al «Historial». Va con lo más reciente arriba y en tres colores, como en Woo:
    - ámbar para las notas privadas;
    - azul para las notas para el cliente;
    - gris para lo que apunta el sistema (pagado, enviado, emails…).
  - Cada nota dice quién la escribió (`order_events.author`).
  - **«Añadir nota»** (`Orders\Application\AddOrderNote`), de hasta 500 caracteres:
    - **Nota privada**: solo la ve el equipo.
    - **Nota para el cliente**: le llega por email (`OrderNoteMail`) y la ve en la página de su pedido, en «Actualizaciones del pedido».
  - La nota es un apunte más del pedido (inmutable), como el resto del historial.
  - **«Acciones del pedido»**, un menú con lo que en Woo hay en el desplegable del mismo nombre (`Orders\Application\ResendOrderEmails`):
    - **Enviar los detalles del pedido al cliente**: reenvía la confirmación. Solo si el pedido está pagado.
    - **Reenviar la notificación de nuevo pedido**: el aviso al equipo.
    - **Ver el pedido como el cliente**: abre su página.
    - Los PDF de orden de fabricación y albarán.
  - Cada reenvío queda anotado con su autor.
- **Listado de productos**, con las columnas de Woo:
  - imagen;
  - nombre;
  - SKU (la referencia);
  - inventario;
  - precio;
  - categorías (las colecciones);
  - estado.
- **Filtros del listado**:
  - **Categoría**;
  - **Estado del inventario**: hay existencias, agotado o pocas unidades.
- **Ficha del producto**: los datos van en «Datos del producto», con las pestañas «Variaciones (tallas y colores)» e «Imagen y galería».
- **No cambia ninguna regla de negocio**: es interfaz y dos casos de uso pequeños del módulo `Orders`.

## Consecuencias

- Las notas para el cliente usan el mismo enlace privado del pedido. Nunca llevan datos de otros pedidos.
- Si el email de una nota falla, la nota queda guardada y se apunta `email_failed`.
