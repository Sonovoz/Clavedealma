<x-mail::message>
# Tu pedido ya está en camino

Tu pedido **{{ $order->reference }}** ha salido con **{{ $order->tracking_carrier }}**. Llegará en {{ $order->delivery_min_days }}–{{ $order->delivery_max_days }} días laborables.

Número de seguimiento: **{{ $order->tracking_number }}**

@if ($order->tracking_url)
<x-mail::button :url="$order->tracking_url">
Seguir el envío
</x-mail::button>
@endif

@foreach ($order->lines as $line)
- {{ $line->quantity }} × {{ $line->product_name }}{{ $line->label() ? ' · '.$line->label() : '' }}
@endforeach

**Entrega en:** {{ $order->address_line1 }}@if ($order->address_line2), {{ $order->address_line2 }}@endif, {{ $order->postal_code }} {{ $order->city }}

<x-mail::button :url="route('checkout.thanks', $order->token)" color="success">
Ver mi pedido
</x-mail::button>

Clave de Alma
</x-mail::message>
