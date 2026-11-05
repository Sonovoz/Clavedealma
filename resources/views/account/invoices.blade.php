@extends('account.layout')

@section('account')
    @if ($invoices === [])
        <div class="co-notice"><p>Todavía no tienes facturas. Se emiten solas al confirmarse el pago de cada pedido.</p></div>
    @else
        <table class="co-table co-table--stack">
            <thead><tr><th>Factura</th><th>Fecha</th><th>Pedido</th><th>Total</th><th><span class="co-sr">Descargar</span></th></tr></thead>
            <tbody>
                @foreach ($invoices as $inv)
                    <tr>
                        <td data-label="Factura"><strong>{{ $inv['code'] }}</strong><small class="co-block">{{ $inv['label'] }}</small></td>
                        <td data-label="Fecha">{{ $inv['date'] }}</td>
                        <td data-label="Pedido">{{ $inv['order'] }}</td>
                        <td data-label="Total">{{ $inv['total'] }}</td>
                        <td class="co-table__actions"><a class="co-btn co-btn--small co-btn--ghost" href="{{ $inv['url'] }}">Descargar PDF</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
