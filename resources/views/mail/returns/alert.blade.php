<x-mail::message>
# Devolución {{ $return->reference }}

Pedido {{ $return->order_reference }} · {{ $return->reason->label() }} · {{ \App\Support\Money::fromCents($return->refund_cents)->format() }}

@foreach ($return->lines as $line)
- {{ $line->quantity }} × {{ $line->sku }} · {{ $line->product_name }}
@endforeach

@if ($return->comment)
> {{ $return->comment }}
@endif

<x-mail::button :url="url('/admin/devoluciones')">
Revisar en el panel
</x-mail::button>
</x-mail::message>
