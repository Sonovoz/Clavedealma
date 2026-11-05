# Política de cookies

@include('legal._owner')

Una cookie es un pequeño archivo que la web guarda en tu navegador. Esta web **solo usa cookies técnicas, imprescindibles para funcionar**. No usamos cookies de análisis, publicidad ni redes sociales, y por eso no te pedimos consentimiento (artículo 22.2 de la LSSI-CE y guía de la AEPD).

| Cookie | De quién | Para qué | Duración |
| --- | --- | --- | --- |
| `{{ $sessionCookie }}` | Esta web | Recordar tu carrito y tu pedido mientras compras, y mantener abierta tu sesión de «Mi cuenta» | La sesión (horas) |
| `remember_web_…` | Esta web | Solo si marcas «Recuérdame» al acceder a «Mi cuenta»: no tener que volver a escribir la contraseña | Hasta 400 días, o hasta que salgas |
| `XSRF-TOKEN` | Esta web | Proteger los formularios contra envíos falsificados | La sesión |
@if ($gateway === 'stripe')
| `__stripe_mid`, `__stripe_sid` | Stripe | Solo en las páginas de pago («Finalizar compra» y «Pagar el pedido»): prevenir el fraude en el cobro con tarjeta | Hasta 1 año / 30 minutos |
@endif

@if ($gateway !== 'stripe')
El pago se hace en la página de Redsys, que es otra web con su propia política de cookies.

@endif
Puedes borrar o bloquear las cookies desde la configuración de tu navegador. Si lo haces, no podrás usar el carrito ni pagar.

Si en el futuro usamos cookies que no sean técnicas (por ejemplo, de estadísticas), te pediremos permiso antes y actualizaremos esta política.
