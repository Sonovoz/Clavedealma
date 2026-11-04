@extends('pdf.layout')
@section('title', 'Estado de los envíos')

@section('content')
    <h1>Estado de los envíos</h1>
    <table class="kpis"><tr>
        <td><span class="muted small">En fabricación</span><br><span class="v">{{ $inProduction }}</span></td>
        <td><span class="muted small">Listos para enviar</span><br><span class="v">{{ count($ready) }}</span></td>
        <td><span class="muted small">En camino</span><br><span class="v">{{ count($shipped) }}</span></td>
        <td><span class="muted small">Con retraso</span><br><span class="v {{ $late > 0 ? 'red' : '' }}">{{ $late }}</span></td>
    </tr></table>

    <h2>Listos para enviar</h2>
    @if ($ready === [])<p class="muted">Ninguno.</p>@else
    <table>
        <thead><tr><th>Pedido</th><th>Cliente</th><th>Destino</th><th>Transportista</th><th class="num">Piezas</th><th>Listo desde</th></tr></thead>
        <tbody>@foreach ($ready as $r)<tr><td>{{ $r['reference'] }} @if ($r['test'])<span class="tag">Prueba</span>@endif</td><td>{{ $r['customer'] }}</td><td>{{ $r['city'] }} · {{ $r['zone'] }}</td><td>{{ $r['carrier'] }}</td><td class="num">{{ $r['units'] }}</td><td>{{ $r['ready_at'] }}</td></tr>@endforeach</tbody>
    </table>@endif

    <h2>En camino</h2>
    @if ($shipped === [])<p class="muted">Ninguno.</p>@else
    <table>
        <thead><tr><th>Pedido</th><th>Cliente</th><th>Destino</th><th>Transportista · seguimiento</th><th>Enviado</th><th class="num">Días</th></tr></thead>
        <tbody>@foreach ($shipped as $r)<tr><td>{{ $r['reference'] }} @if ($r['test'])<span class="tag">Prueba</span>@endif</td><td>{{ $r['customer'] }}</td><td>{{ $r['city'] }} · {{ $r['zone'] }}</td><td>{{ $r['carrier'] }} · {{ $r['tracking'] }}</td><td>{{ $r['shipped_at'] }}</td><td class="num {{ $r['late'] ? 'red' : '' }}">{{ $r['days'] }}</td></tr>@endforeach</tbody>
    </table>
    <p class="small muted">Días laborables desde el envío. En rojo, los que pasan del plazo de entrega del destino: conviene mirar el seguimiento.</p>@endif
@endsection
