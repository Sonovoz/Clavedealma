@php($legal = $f($issuer->legal_name, 'razón social'))
@if ($store)
La tienda oficial de **{{ $store['artist'] }}** ({{ $store['url'] }}) forma parte de Clave de Alma y la opera **{!! $legal !!}**, que es quien vende, cobra, envía y factura todos los pedidos. El artista no es parte del contrato de compraventa ni trata los datos de los clientes.
@else
Clave de Alma ({{ $site }}) es una plataforma de tiendas oficiales de artistas. Todas las tiendas las opera **{!! $legal !!}**, que es quien vende, cobra, envía y factura todos los pedidos.
@endif
