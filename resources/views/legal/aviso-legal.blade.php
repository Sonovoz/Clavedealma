# Aviso legal

@include('legal._owner')

## 1. Titular del sitio web

En cumplimiento del artículo 10 de la Ley 34/2002, de servicios de la sociedad de la información y de comercio electrónico (LSSI-CE):

| | |
| --- | --- |
| Titular | {!! $f($issuer->legal_name, 'razón social') !!} |
| NIF | {!! $f($issuer->nif, 'NIF') !!} |
| Domicilio | {!! $f($issuer->address, 'domicilio') !!}, {!! $f($issuer->postal_code, 'código postal') !!} {!! $f($issuer->city, 'población') !!} |
| Email | {!! $f($issuer->email, 'email de contacto') !!} |
| Teléfono | {!! $f($issuer->phone, 'teléfono') !!} |
| Datos registrales | {!! $f($issuer->registry_details, 'datos registrales (Registro Mercantil)') !!} |

## 2. Objeto

Este sitio permite conocer y comprar la ropa y los productos oficiales de los artistas que tienen tienda en Clave de Alma. Navegar por él no exige registrarse. Comprar implica aceptar las [condiciones de venta](condiciones-de-venta).

## 3. Propiedad intelectual e industrial

Los textos, el diseño del sitio y su código pertenecen al titular. Los nombres, marcas, imágenes, diseños y obras de cada artista pertenecen a sus titulares y se usan con su autorización para su tienda oficial. No está permitido reproducirlos, distribuirlos ni transformarlos sin permiso de sus titulares.

## 4. Responsabilidad

El titular cuida que la información del sitio sea correcta y esté al día, y corrige los errores en cuanto los conoce. No responde del mal uso que terceros hagan del sitio ni del contenido de los sitios externos enlazados.

## 5. Legislación y jurisdicción

Este sitio se rige por la legislación española. En los conflictos con consumidores serán competentes los juzgados y tribunales del domicilio del consumidor.
