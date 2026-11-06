<x-mail::message>
# Comisión Minimedia · {{ $label }}

Resumen de las ventas de todas las tiendas de Clave de Alma en {{ mb_strtolower($label) }} y la comisión de Minimedia ({{ $rate }}).

<x-mail::table>
| | |
| :-- | --: |
| Pedidos cobrados | {{ $orders }} |
| Ventas sin IVA ni envío | {{ $sales }} |
| Devoluciones del mes (sin IVA) | − {{ $refunds }} |
| **Neto** | **{{ $net }}** |
| **Comisión Minimedia ({{ $rate }})** | **{{ $commission }}** |
</x-mail::table>

@if ($stores !== [])
**Por tienda**

<x-mail::table>
| Tienda | Pedidos | Neto | Comisión |
| :-- | --: | --: | --: |
@foreach ($stores as $s)
| {{ $s['store'] }} | {{ $s['orders'] }} | {{ $s['net'] }} | {{ $s['commission'] }} |
@endforeach
</x-mail::table>
@else
Este mes no ha habido ventas.
@endif

Van adjuntos el PDF y el CSV (para la hoja de cálculo). Las compras de prueba no cuentan. Este resumen no es una factura.

Clave de Alma
</x-mail::message>
