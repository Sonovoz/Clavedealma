# Envíos y devoluciones

@include('legal._owner')

## Envíos

Elegimos la empresa de transporte de cada destino. El precio del envío depende del **destino y del peso del pedido** y se indica antes de pagar.

@if ($shipping === [])
{!! $f(null, 'tarifas de envío activas (Envíos → Tarifas por destino)') !!}
@else
| Destino | Envío desde | Envío gratis | Entrega después de fabricar |
| --- | --- | --- | --- |
@foreach ($shipping as $z)
| {{ $z['zone'] }} | {{ $z['from'] }} | {{ $z['free'] ? 'Pedidos desde '.$z['free'] : '—' }} | {{ $z['days'] }} días laborables |
@endforeach
@endif

@if ($blocked !== [])
De momento **no enviamos a {{ implode(', ', $blocked) }}**.
@endif

- **Plazo total**: el de fabricación de cada pieza, que se ve en su ficha, más el de entrega del destino. Si el pedido tiene varias piezas, sale junto cuando están todas.
- **Seguimiento**: cuando el pedido sale te enviamos un email con el número de seguimiento. También lo ves en la página de tu pedido.

## Devoluciones

Tienes **14 días naturales desde la entrega** para devolver las piezas no personalizadas (ver las [condiciones de venta](condiciones-de-venta)).

1. Entra en la página de tu pedido (el enlace «Ver mi pedido» de tus emails) y pulsa **Devolver piezas**.
2. Te enviaremos por email las instrucciones para mandarnos el paquete. La dirección de devoluciones es {!! $f($returnsAddress, 'dirección de devoluciones (RETURNS_ADDRESS)') !!}.
3. Cuando lo recibamos y lo revisemos, te devolveremos el dinero al mismo medio de pago. Si devuelves el pedido entero, también te devolvemos el envío que pagaste.

El envío de vuelta corre de tu cuenta, salvo que la pieza llegue con un defecto o no sea la que pediste. Las piezas personalizadas no admiten devolución, salvo defecto.
