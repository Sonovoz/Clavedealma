# ADR 0021 · «Mi cuenta»: cuenta opcional del cliente, como WooCommerce

- Estado: aceptado
- Fecha: 2026-11-05

## Contexto

El equipo viene de WooCommerce y quiere que la tienda se maneje igual. Hasta ahora el cliente compraba solo como invitado y seguía su pedido con el enlace privado del email.

## Decisión

**La cuenta es opcional.** Se puede seguir comprando como invitado. Quien quiera, crea una cuenta en `/mi-cuenta` o marcando «¿Crear una cuenta?» al finalizar la compra.

- **Mismas pantallas y nombres que WooCommerce**:
  - sin sesión, «Acceder» y «Registrarse» lado a lado, con «Recuérdame» y «¿Has olvidado tu contraseña?»;
  - con sesión, el menú Escritorio, Pedidos, Facturas, Devoluciones, Direcciones, Detalles de la cuenta y Salir;
  - las direcciones: `/mi-cuenta/pedidos`, `/mi-cuenta/ver-pedido/<pedido>`, `/mi-cuenta/editar-direccion`, `/mi-cuenta/editar-cuenta`;
  - en «Finalizar compra», «¿Ya eres cliente? Haz clic aquí para acceder» y «¿Crear una cuenta?».

  Facturas y Devoluciones no están en WooCommerce básico, pero sí en los plugins habituales, y aquí son parte del servicio.
- **Es una cuenta de Clave de Alma, no de cada tienda**: un solo vendedor, un solo carrito y una sola cuenta. Las páginas usan la imagen de la compra (`checkout.layout`).
- **Los clientes son usuarios con el rol Cliente** (`Identity`, `RegisterCustomer`). Nunca entran en el panel (`Role::canAccessAdminPanel`). En el panel están en *Comercio → Clientes* (solo superadmin, solo lectura), como en WooCommerce. *Herramientas → Usuarios* queda para el equipo.
- **El pedido guarda quién lo hizo** (`orders.user_id`, vacío = invitado). Se fija al crearlo y no cambia, porque el pedido es inmutable.
- **Qué pedidos ve cada cliente** (`App\Storefront\Account\CustomerOrders`):
  - los que hizo con la sesión iniciada;
  - los que hizo como invitado con su email, **solo si lo ha confirmado** con el enlace que se le envía al registrarse.

  Así nadie ve pedidos ajenos registrándose con un email que no es suyo. Cambiar el email pide la contraseña actual y obliga a confirmarlo otra vez.
- **Una dirección guardada** (`customer_addresses`), la «Dirección de facturación» de WooCommerce. Clave de Alma usa la misma para facturar y enviar: las facturas son simplificadas.
  - Rellena «Finalizar compra».
  - Cada pedido con sesión la actualiza, como en WooCommerce.
  - El pedido guarda su propia copia, así que cambiarla no toca pedidos ya hechos.
- **«Ver pedido»** enseña lo mismo que «Pedido recibido» (`App\Storefront\Checkout\OrderPage`): seguimiento, facturas, devoluciones y detalles.
- **Seguridad**:
  - límites de intentos en acceder, registrarse y recuperar la contraseña;
  - los mensajes no revelan si un email tiene cuenta;
  - contraseñas de al menos 8 caracteres, siempre cifradas;
  - sesión nueva al acceder;
  - emails de confirmación y de contraseña en español (`IdentityServiceProvider`).
- **Textos legales**: la privacidad recoge los datos de la cuenta y cuánto se guardan, y las cookies, `remember_web_…` («Recuérdame»).

## Consecuencias

- «Finalizar compra» sigue funcionando igual para invitados.
- Si más adelante hace falta «Enviar a una dirección diferente», se añade una segunda dirección (de envío) a la cuenta y al pedido, con su migración.
- Borrar una cuenta es, de momento, una petición por email (derechos RGPD). Los pedidos y las facturas se conservan lo que exige la ley.
