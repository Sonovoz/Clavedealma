# Política de privacidad

@include('legal._owner')

## 1. Responsable del tratamiento

| | |
| --- | --- |
| Responsable | {!! $f($issuer->legal_name, 'razón social') !!} |
| NIF | {!! $f($issuer->nif, 'NIF') !!} |
| Domicilio | {!! $f($issuer->address, 'domicilio') !!}, {!! $f($issuer->postal_code, 'código postal') !!} {!! $f($issuer->city, 'población') !!} |
| Contacto | {!! $f($issuer->email, 'email de contacto') !!} |

## 2. Qué datos tratamos, para qué y con qué base

| Datos | Para qué | Base legal |
| --- | --- | --- |
| Nombre, email, teléfono y dirección de entrega | Gestionar tu pedido: fabricarlo, enviarlo, avisarte de cada paso y atender devoluciones | Ejecución del contrato de compraventa (art. 6.1.b RGPD) |
| Datos del pedido y de la factura | Facturar y cumplir las obligaciones fiscales y contables | Obligación legal (art. 6.1.c RGPD) |
| Si creas una cuenta en «Mi cuenta» (es opcional): email, nombre, contraseña (guardada cifrada, nadie puede leerla) y la dirección que guardes | Darte acceso a tus pedidos, facturas y devoluciones y rellenar tus datos al comprar | Ejecución del contrato que aceptas al registrarte (art. 6.1.b RGPD) |
| Mensajes que nos envíes | Responderte | Ejecución del contrato o tu consentimiento |
| Datos de acceso al panel de gestión (solo personal autorizado): usuario, IP y fecha | Seguridad del sistema | Interés legítimo (art. 6.1.f RGPD) |

**No pedimos ni guardamos los datos de tu tarjeta**: {{ $gateway === 'stripe' ? 'el pago lo hace Stripe en su propio formulario.' : 'el pago lo haces en la página segura de Redsys, la plataforma de pago de los bancos.' }} **No enviamos comunicaciones comerciales**, no elaboramos perfiles y no usamos tus datos para tomar decisiones automatizadas.

## 3. Quién recibe tus datos

@if ($gateway === 'stripe')
- **Stripe** (pasarela de pago), para cobrar el pedido. Stripe puede tratar datos fuera del Espacio Económico Europeo con las garantías que exige el RGPD.
@else
- **Redsys y nuestro banco** (pasarela de pago), para cobrar el pedido con tarjeta o Bizum. Reciben el importe y el número del pedido; los datos de la tarjeta los escribes tú directamente en Redsys.
@endif
- **La empresa de transporte** de tu destino, que recibe tu nombre, tu dirección y tu teléfono para entregar el paquete.
- **Los proveedores tecnológicos** que alojan la web y envían los emails, como encargados del tratamiento: {!! $f(null, 'proveedores de alojamiento y de correo (los indica la asesoría)') !!}.
- **La Administración Tributaria**, cuando la ley lo exija.

**El taller que fabrica las piezas no recibe tus datos personales**: solo ve qué fabricar. **Los artistas tampoco**: solo reciben datos de ventas sin identificar a nadie. Nunca vendemos ni cedemos tus datos.

## 4. Cuánto tiempo los guardamos

- **Pedidos y facturas**: los plazos que marca la ley mercantil y fiscal.
- **Cuenta de cliente**: mientras la mantengas. Si nos pides borrarla, borramos la cuenta y la dirección guardada; los pedidos y las facturas se conservan lo que exige la ley.
- **Registro de seguridad del panel**: {{ $securityDays }} días.
- **Mensajes**: lo necesario para atenderte.

## 5. Tus derechos

Puedes pedir el acceso, la rectificación, la supresión, la oposición, la limitación o la portabilidad de tus datos escribiendo a {!! $f($issuer->email, 'email de contacto') !!}. Si crees que no los tratamos bien, puedes reclamar ante la Agencia Española de Protección de Datos (www.aepd.es).

## 6. Cookies

Consulta la [política de cookies](cookies).
