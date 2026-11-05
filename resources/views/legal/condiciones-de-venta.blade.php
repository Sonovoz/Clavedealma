# Condiciones de venta

@include('legal._owner')

## 1. Vendedor

{!! $f($issuer->legal_name, 'razón social') !!}, NIF {!! $f($issuer->nif, 'NIF') !!}, con domicilio en {!! $f($issuer->address, 'domicilio') !!}, {!! $f($issuer->postal_code, 'código postal') !!} {!! $f($issuer->city, 'población') !!}. Contacto: {!! $f($issuer->email, 'email de contacto') !!}.

Estas condiciones se aplican a todas las compras hechas en Clave de Alma y en las tiendas oficiales de sus artistas. Al hacer un pedido las aceptas, y quedan guardadas con él.

## 2. Productos y precios

- Cada pieza **se fabrica para ti cuando haces el pedido**, salvo las que se indican como disponibles en stock. El plazo de fabricación aparece en la ficha de cada pieza.
- Los precios están en euros e **incluyen el IVA**. El coste del envío se indica antes de pagar, según el destino y el peso del pedido (ver [envíos y devoluciones](envios-y-devoluciones)).
- Los precios rebajados muestran el precio más bajo de los 30 días anteriores a la rebaja.

## 3. Cómo se compra

1. Añade las piezas al carrito. Hay un solo carrito para todas las tiendas.
2. Escribe tus datos y la dirección de entrega: verás el precio del envío y el total.
3. Acepta estas condiciones y paga.
4. Al confirmarse el pago recibirás un email con el resumen del pedido y el enlace para seguirlo y descargar tu factura.

El contrato se hace en español y queda archivado: puedes consultarlo en cualquier momento desde el enlace de tu pedido.

## 4. Pago

El pago se hace de forma segura con **Stripe**, con tarjeta o con los demás métodos que se muestren al finalizar la compra. Los datos de la tarjeta los recoge Stripe y nunca llegan a nuestro servidor. El pedido se confirma cuando Stripe confirma el cobro.

## 5. Entrega

Enviamos a los destinos y en los plazos que se indican en [envíos y devoluciones](envios-y-devoluciones), con la empresa de transporte que elegimos para cada destino. Cuando el pedido sale, te enviamos el número de seguimiento.

Si una pieza no puede servirse (por ejemplo, porque se agotó mientras pagabas), te avisaremos y te devolveremos lo pagado por ella.

## 6. Derecho de desistimiento

Tienes **14 días naturales desde que recibes el pedido** para devolver las piezas sin dar ninguna explicación (artículos 102 y siguientes del texto refundido de la Ley General para la Defensa de los Consumidores y Usuarios).

- **Excepción**: las piezas **personalizadas o hechas según tus indicaciones** no tienen derecho de desistimiento (art. 103.c). Se avisa en la ficha de la pieza y en la cesta.
- **Cómo ejercerlo**: desde la página de tu pedido, con el botón «Devolver piezas», o enviando el formulario de abajo a {!! $f($issuer->email, 'email de contacto') !!}.
- **Devolución de las piezas**: envíalas sin usar y con sus etiquetas, siguiendo las instrucciones que te mandaremos, en los 14 días siguientes a comunicarnos el desistimiento. El coste del envío de vuelta corre de tu cuenta, salvo que la pieza tenga un defecto o no sea la que pediste.
- **Reembolso**: te devolveremos lo pagado al mismo medio de pago en un máximo de 14 días desde que nos comuniques el desistimiento. Podemos esperar a recibir las piezas. Si devuelves el pedido entero, también te devolvemos el envío que pagaste (el ordinario).

## 7. Garantía

Las piezas tienen la **garantía legal de 3 años** desde la entrega por falta de conformidad. Si una pieza llega con un defecto, escríbenos a {!! $f($issuer->email, 'email de contacto') !!} y la repararemos, la sustituiremos o te devolveremos su importe.

## 8. Atención al cliente y reclamaciones

Puedes escribirnos a {!! $f($issuer->email, 'email de contacto') !!}. Te responderemos lo antes posible.

## 9. Legislación aplicable

Estas condiciones se rigen por la legislación española. En caso de conflicto, el consumidor puede acudir a los juzgados y tribunales de su domicilio.

## Formulario de desistimiento

> A la atención de {!! $f($issuer->legal_name, 'razón social') !!}, {!! $f($issuer->address, 'domicilio') !!}, {!! $f($issuer->postal_code, 'código postal') !!} {!! $f($issuer->city, 'población') !!} · {!! $f($issuer->email, 'email de contacto') !!}
>
> Por la presente le comunico que desisto de mi contrato de venta de los siguientes bienes: ……
>
> Pedido número: …… · Recibido el: ……
>
> Nombre: …… · Dirección: ……
>
> Firma (solo si se envía en papel) · Fecha: ……
