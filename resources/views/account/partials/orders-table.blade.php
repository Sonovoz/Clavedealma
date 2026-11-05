{{-- Tabla de pedidos como en WooCommerce: Pedido · Fecha · Estado · Total · Acciones. Variables: $rows. --}}
<table class="co-table co-table--stack">
    <thead>
        <tr><th>Pedido</th><th>Fecha</th><th>Estado</th><th>Total</th><th><span class="co-sr">Acciones</span></th></tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            <tr>
                <td data-label="Pedido"><a href="{{ $row['view'] }}"><strong>{{ $row['reference'] }}</strong></a></td>
                <td data-label="Fecha">{{ $row['date'] }}</td>
                <td data-label="Estado"><span class="co-status co-status--{{ $row['statusKey'] }}">{{ $row['status'] }}</span></td>
                <td data-label="Total">{{ $row['total'] }}</td>
                <td class="co-table__actions">
                    @if ($row['pay'])<a class="co-btn co-btn--small" href="{{ $row['pay'] }}">Pagar</a>@endif
                    <a class="co-btn co-btn--small co-btn--ghost" href="{{ $row['view'] }}">Ver</a>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
