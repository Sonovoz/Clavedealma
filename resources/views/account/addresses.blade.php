@extends('account.layout')

{{-- «Direcciones» de WooCommerce. Clave de Alma usa la misma dirección para facturar y enviar. --}}
@section('account')
    <p>La siguiente dirección se usará por defecto en la página de finalizar compra.</p>
    <div class="co-card co-address-card">
        <header>
            <h2 class="co-h2">Dirección de facturación y envío</h2>
            <a class="co-link" href="{{ route('account.address.edit') }}">{{ $address ? 'Editar' : 'Añadir' }}</a>
        </header>
        @if ($address)
            <address class="co-address">
                {{ $address->name }}<br>{{ $address->address_line1 }}@if ($address->address_line2), {{ $address->address_line2 }}@endif<br>
                {{ $address->postal_code }} {{ $address->city }}@if ($address->province) ({{ $address->province }})@endif · {{ \App\Storefront\Checkout\Countries::ALL[$address->country] ?? $address->country }}<br>
                {{ $address->phone }}
            </address>
        @else
            <p class="co-fine">Todavía no has guardado ninguna dirección. Se guarda sola con tu primer pedido.</p>
        @endif
    </div>
@endsection
