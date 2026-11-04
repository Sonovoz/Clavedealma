@extends('checkout.layout', ['step' => 0])

@section('content')
    <article class="co-card co-legal">
        <h1 class="co-h1">{{ $title }}</h1>
        <p class="co-alert">Texto pendiente: lo redacta la asesoría legal de Clave de Alma antes de abrir la venta al público.</p>
        @if ($page === 'envios-y-devoluciones')
            <p>Mientras tanto, lo que ya aplica la tienda: Clave de Alma elige la empresa de transporte de cada destino, el envío se paga según el peso del pedido y las piezas no personalizadas se pueden devolver en los 14 días siguientes a la entrega.</p>
        @endif
    </article>
@endsection
