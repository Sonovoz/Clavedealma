@extends('pdf.layout')
@section('title', count($orders) === 1 ? 'Orden de fabricación '.$orders[0]['reference'] : 'Hoja de fabricación')
@section('footer', 'Orden de fabricación · sin datos del cliente')

@section('content')
    <h1>{{ count($orders) === 1 ? 'Orden de fabricación '.$orders[0]['reference'] : 'Hoja de fabricación' }}</h1>
    <p class="muted">{{ count($orders) }} {{ count($orders) === 1 ? 'pedido' : 'pedidos' }} · {{ $units }} {{ $units === 1 ? 'pieza' : 'piezas' }}. Marca cada pieza al terminarla.</p>

    @if (count($orders) > 1)
        <h2>Total por referencia</h2>
        <table>
            <thead><tr><th>SKU</th><th>Pieza</th><th>Talla · color</th><th>Tienda</th><th class="num">Ud.</th></tr></thead>
            <tbody>
                @foreach ($totals as $t)
                    <tr><td>{{ $t['sku'] }}</td><td>{{ $t['name'] }} @if ($t['personalized'])<span class="tag">Personalizada</span>@endif</td><td>{{ $t['option'] }}</td><td>{{ $t['store'] }}</td><td class="num"><strong>{{ $t['quantity'] }}</strong></td></tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @forelse ($orders as $order)
        <div class="block">
            <h2>{{ $order['reference'] }} @if ($order['test'])<span class="tag">Prueba</span>@endif</h2>
            <p class="small">Pagado {{ $order['paid_at'] }} · Estado: {{ $order['status'] }} · Debe estar listo: <span @class(['red' => $order['late']])>{{ $order['deadline'] }}</span></p>
            <table>
                <thead><tr><th class="check">Hecho</th><th>SKU</th><th>Pieza</th><th>Talla · color</th><th>Tienda</th><th class="num">Ud.</th></tr></thead>
                <tbody>
                    @foreach ($order['lines'] as $l)
                        <tr><td class="check"><span class="box"></span></td><td>{{ $l['sku'] }}</td><td>{{ $l['name'] }} @if ($l['personalized'])<span class="tag">Personalizada</span>@endif</td><td>{{ $l['option'] }}</td><td>{{ $l['store'] }}</td><td class="num"><strong>{{ $l['quantity'] }}</strong></td></tr>
                    @endforeach
                </tbody>
            </table>
            @if ($order['note'])<p class="small"><strong>Nota del pedido:</strong> {{ $order['note'] }}</p>@endif
        </div>
    @empty
        <p>No hay nada pendiente de fabricar.</p>
    @endforelse
@endsection
