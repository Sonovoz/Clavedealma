@php($m = fn (int $c): string => \App\Support\Money::fromCents($c)->format())
<x-mail::message>
# Gracias, {{ \Illuminate\Support\Str::before($order->customer_name, ' ') }}

Hemos recibido el pago de tu pedido **{{ $order->reference }}**. Empezamos a prepararlo.

<x-mail::table>
| Artículo | Ud. | Importe |
| :-- | :-: | --: |
@foreach ($order->lines as $line)
| {{ $line->product_name }}{{ $line->label() ? ' · '.$line->label() : '' }}<br><small>{{ $line->store_name }}</small> | {{ $line->quantity }} | {{ $m($line->total_cents + $line->discount_cents) }} |
@endforeach
@if ($order->discount_cents > 0)
| Cupón {{ $order->coupon_code }} | | −{{ $m($order->discount_cents) }} |
@endif
| Envío ({{ $order->shipping_zone->label() }}) | | {{ $order->shipping_free ? 'Gratis' : $m($order->shipping_cents) }} |
| **Total** | | **{{ $m($order->total_cents) }}** |
</x-mail::table>

IVA incluido ({{ $m($order->vat_cents) }}).

**Plazo:** cada pieza se fabrica para ti en {{ $order->lead_time_min_days }}–{{ $order->lead_time_max_days }} días laborables y el envío tarda {{ $order->delivery_min_days }}–{{ $order->delivery_max_days }} días más. Te avisaremos cuando salga.

**Entrega en:**
{{ $order->customer_name }}<br>
{{ $order->address_line1 }}@if ($order->address_line2), {{ $order->address_line2 }}@endif<br>
{{ $order->postal_code }} {{ $order->city }}@if ($order->province) ({{ $order->province }})@endif

<x-mail::button :url="route('checkout.thanks', $order->token)">
Ver mi pedido
</x-mail::button>

Tienes 14 días desde la entrega para devolver las piezas que no sean personalizadas.

Clave de Alma
</x-mail::message>
