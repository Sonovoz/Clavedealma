<x-mail::message>
# Novedades de tu pedido {{ $order->reference }}

Hola, {{ \Illuminate\Support\Str::before($order->customer_name, ' ') }}:

{{ $text }}

<x-mail::button :url="route('checkout.thanks', $order->token)">
Ver mi pedido
</x-mail::button>

Clave de Alma
</x-mail::message>
