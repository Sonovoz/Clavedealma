@php($m = fn (int $c): string => \App\Support\Money::fromCents($c)->format())
<x-mail::message>
# Pedido {{ $order->reference }}@if ($order->is_test) (prueba)@endif

**{{ $m($order->total_cents) }}** · {{ $order->itemCount() }} ud. · {{ $order->shipping_zone->label() }} ({{ $order->shipping_carrier }})

@foreach ($order->lines as $line)
- {{ $line->quantity }} × {{ $line->sku }} · {{ $line->product_name }} ({{ $line->store_name }})
@endforeach

@if ($order->review_reason)
**A revisar:** {{ $order->review_reason }}
@endif

<x-mail::button :url="url('/admin/pedidos')">
Abrir en el panel
</x-mail::button>
</x-mail::message>
