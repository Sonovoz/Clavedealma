@php($m = fn (int $c): string => \App\Support\Money::fromCents($c)->format())
<x-mail::message>
@switch($kind)
@case('requested')
# Hemos recibido tu solicitud

Hola, {{ \Illuminate\Support\Str::before($name, ' ') }}. Hemos recibido tu solicitud de devolución **{{ $return->reference }}** del pedido {{ $return->order_reference }}. La revisamos y te escribimos con los pasos para enviarnos las piezas.
@break
@case('approved')
# Devolución aprobada

Tu devolución **{{ $return->reference }}** está aprobada. Para enviarnos las piezas:

{{ $return->instructions }}

Cuando las recibamos y comprobemos, te devolveremos **{{ $m($return->refund_cents) }}** al mismo medio de pago.
@break
@case('rejected')
# Sobre tu solicitud de devolución

No hemos podido aceptar la devolución **{{ $return->reference }}**.

**Motivo:** {{ $return->rejection_reason }}

Si tienes cualquier duda, responde a este email.
@break
@default
# Te hemos devuelto el dinero

Hemos recibido las piezas de la devolución **{{ $return->reference }}** y te hemos devuelto **{{ $m($return->refund_cents) }}** al mismo medio de pago. Según tu banco, puede tardar unos días en verse en tu cuenta.
@endswitch

@foreach ($return->lines as $line)
- {{ $line->quantity }} × {{ $line->product_name }}{{ $line->option ? ' · '.$line->option : '' }}
@endforeach

<x-mail::button :url="$orderUrl">
Ver mi pedido
</x-mail::button>

Clave de Alma
</x-mail::message>
