@extends('account.layout')

@section('account')
    <p class="co-fine">Para devolver piezas abre el pedido en <a href="{{ route('account.orders') }}">Pedidos</a> y pulsa «Devolver piezas». Tienes 14 días desde la entrega, salvo en piezas personalizadas.</p>
    @if ($returns === [])
        <div class="co-notice"><p>No tienes devoluciones.</p></div>
    @else
        <table class="co-table co-table--stack">
            <thead><tr><th>Devolución</th><th>Fecha</th><th>Pedido</th><th>Estado</th><th>Importe</th></tr></thead>
            <tbody>
                @foreach ($returns as $r)
                    <tr>
                        <td data-label="Devolución"><strong>{{ $r['reference'] }}</strong></td>
                        <td data-label="Fecha">{{ $r['date'] }}</td>
                        <td data-label="Pedido">{{ $r['order'] }}</td>
                        <td data-label="Estado">{{ $r['status'] }}</td>
                        <td data-label="Importe">{{ $r['amount'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
