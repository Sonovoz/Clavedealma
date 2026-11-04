<x-mail::message>
# Hemos cancelado tu pedido

Sentimos decirte que hemos tenido que cancelar tu pedido **{{ $order->reference }}**.

**Motivo:** {{ $reason }}

Te hemos devuelto **{{ \App\Support\Money::fromCents($order->total_cents)->format() }}** al mismo medio de pago. Según tu banco, puede tardar unos días en verse en tu cuenta.

<x-mail::button :url="route('checkout.thanks', $order->token)">
Ver mi pedido
</x-mail::button>

Clave de Alma
</x-mail::message>
