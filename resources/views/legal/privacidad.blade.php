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
| Mensajes que nos envíes | Responderte | Ejecución del contrato o tu consentimiento |
| Datos de acceso al panel de gestión (solo personal autorizado): usuario, IP y fecha | Seguridad del sistema | Interés legítimo (art. 6.1.f RGPD) |

**No pedimos ni guardamos los datos de tu tarjeta**: el pago lo hace Stripe en su propio formulario. **No enviamos comunicaciones comerciales**, no elaboramos perfiles y no usamos tus datos para tomar decisiones automatizadas.

## 3. Quién recibe tus datos

- **Stripe** (pasarela de pago), para cobrar el pedido. Stripe puede tratar datos fuera del Espacio Económico Europeo con las garantías que exige el RGPD.
- **La empresa de transporte** de tu destino, que recibe tu nombre, tu dirección y tu teléfono para entregar el paquete.
- **Los proveedores tecnológicos** que alojan la web y envían los emails, como encargados del tratamiento: {!! $f(null, 'proveedores de alojamiento y de correo (los indica la asesoría)') !!}.
- **La Administración Tributaria**, cuando la ley lo exija.

**El taller que fabrica las piezas no recibe tus datos personales**: solo ve qué fabricar. **Los artistas tampoco**: solo reciben datos de ventas sin identificar a nadie. Nunca vendemos ni cedemos tus datos.

## 4. Cuánto tiempo los guardamos

- **Pedidos y facturas**: los plazos que marca la ley mercantil y fiscal.
- **Registro de seguridad del panel**: {{ $securityDays }} días.
- **Mensajes**: lo necesario para atenderte.

## 5. Tus derechos

Puedes pedir el acceso, la rectificación, la supresión, la oposición, la limitación o la portabilidad de tus datos escribiendo a {!! $f($issuer->email, 'email de contacto') !!}. Si crees que no los tratamos bien, puedes reclamar ante la Agencia Española de Protección de Datos (www.aepd.es).

## 6. Cookies

Consulta la [política de cookies](cookies).
