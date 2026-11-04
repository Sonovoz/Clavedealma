@php
    use App\Modules\Orders\Contracts\OrderStatus;
    $paid = $order->status->isPaid();
    $steps = [
        ['label' => 'Pago recibido', 'at' => $order->paid_at],
        ['label' => 'Fabricando tus piezas', 'at' => $order->production_started_at],
        ['label' => 'Preparando el envío', 'at' => $order->ready_at],
        ['label' => 'En camino', 'at' => $order->shipped_at],
        ['label' => 'Entregado', 'at' => $order->delivered_at],
    ];
@endphp
@extends('checkout.layout', ['step' => $paid ? 4 : 3])

@if ($order->status === OrderStatus::PendingPayment)
    @push('head')
        {{-- Esperando la confirmación de la pasarela: la página se recarga sola. --}}
        <meta http-equiv="refresh" content="4">
    @endpush
@endif

@section('content')
    <div class="co-grid">
        <section class="co-card co-thanks">
            @if ($paid)
                <p class="co-kicker">{{ $order->status->customerLabel() }}</p>
                @if ($order->status === OrderStatus::Paid)
                    <h1 class="co-h1">Gracias, {{ \Illuminate\Support\Str::before($order->customer_name, ' ') }}</h1>
                    <p>Hemos recibido el pago de tu pedido <strong>{{ $order->reference }}</strong>. Te hemos enviado la confirmación a <strong>{{ $order->email }}</strong>.</p>
                @else
                    <h1 class="co-h1">Pedido {{ $order->reference }}</h1>
                @endif

                <ol class="co-track" aria-label="Estado del pedido">
                    @foreach ($steps as $s)
                        <li @class(['is-done' => $s['at'] !== null])>
                            <span class="co-track__dot" aria-hidden="true"></span>
                            <span class="co-track__label">{{ $s['label'] }}</span>
                            <span class="co-track__date">{{ $s['at']?->timezone('Europe/Madrid')->format('d/m/Y') }}</span>
                        </li>
                    @endforeach
                </ol>

                @if ($order->tracking_number)
                    <p>Tu pedido va con <strong>{{ $order->tracking_carrier }}</strong>. Número de seguimiento: <strong>{{ $order->tracking_number }}</strong>.</p>
                    @if ($order->tracking_url)
                        <a class="co-btn" href="{{ $order->tracking_url }}" target="_blank" rel="noopener">Seguir el envío</a>
                    @endif
                @elseif ($order->status !== OrderStatus::Delivered)
                    <p>Cada pieza se fabrica para ti en {{ $order->lead_time_min_days }}–{{ $order->lead_time_max_days }} días laborables y el envío tarda {{ $order->delivery_min_days }}–{{ $order->delivery_max_days }} días más. Te avisaremos por email cuando salga.</p>
                @endif
                @if (session('return.created'))
                    <p class="co-ok" role="status">Hemos recibido tu solicitud de devolución {{ session('return.created') }}. Te escribiremos por email con los pasos.</p>
                @endif
                @if (session('return.error'))
                    <p class="co-alert" role="alert">{{ session('return.error') }}</p>
                @endif
                @if ($invoices !== [])
                    <h2 class="co-h2">Facturas</h2>
                    <ul class="co-returns">
                        @foreach ($invoices as $inv)
                            <li><span><a href="{{ $inv['url'] }}">{{ $inv['label'] }} {{ $inv['code'] }}</a></span><span>{{ $inv['total'] }}</span></li>
                        @endforeach
                    </ul>
                @endif
                @if ($returns !== [])
                    <h2 class="co-h2">Devoluciones</h2>
                    <ul class="co-returns">
                        @foreach ($returns as $r)
                            <li><span><strong>{{ $r['reference'] }}</strong> · {{ $r['status'] }}</span><span>{{ $r['amount'] }}</span></li>
                        @endforeach
                    </ul>
                @endif
                @if ($canReturn)
                    <p class="co-fine">¿Algo no te convence? Puedes devolver las piezas no personalizadas hasta el {{ $returnDeadline }}.</p>
                    <a class="co-btn co-btn--ghost" href="{{ route('returns.create', $order->token) }}">Devolver piezas</a>
                @endif
                @if ($order->is_test)
                    <p class="co-fine co-test">Compra de prueba: no se ha cobrado dinero real.</p>
                @endif
                <a class="co-link" href="{{ route('market') }}">Volver a Clave de Alma</a>
            @elseif ($order->status === OrderStatus::Cancelled)
                <h1 class="co-h1">Pedido cancelado</h1>
                @if ($order->paid_at)
                    <p>Hemos cancelado el pedido {{ $order->reference }} y te hemos devuelto {{ $total }} al mismo medio de pago. Según tu banco, puede tardar unos días en verse.</p>
                @else
                    <p>El pedido {{ $order->reference }} se canceló porque no se completó el pago. No se ha cobrado nada.</p>
                    <a class="co-btn" href="{{ route('cart.show') }}">Volver a la cesta</a>
                @endif
            @else
                <h1 class="co-h1">Confirmando el pago…</h1>
                <p>Estamos esperando la confirmación del pago de tu pedido {{ $order->reference }}. Esta página se actualiza sola.</p>
                <a class="co-link" href="{{ route('checkout.pay', $order->token) }}">¿Algo ha fallado? Volver al pago</a>
            @endif
        </section>

        @include('checkout.partials.summary')
    </div>
@endsection
