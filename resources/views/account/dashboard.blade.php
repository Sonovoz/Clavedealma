@extends('account.layout')

{{-- Escritorio de «Mi cuenta», con el saludo de WooCommerce. --}}
@section('account')
    @php($first = \Illuminate\Support\Str::before($user->name, ' '))
    <form class="co-hello" method="post" action="{{ route('account.logout') }}">
        @csrf
        Hola <strong>{{ $first }}</strong> (¿no eres {{ $first }}? <button class="co-link" type="submit">Cerrar sesión</button>)
    </form>
    <p>Desde el escritorio de tu cuenta puedes ver tus <a href="{{ route('account.orders') }}">pedidos recientes</a>, descargar tus <a href="{{ route('account.invoices') }}">facturas</a>, gestionar tu <a href="{{ route('account.addresses') }}">dirección de envío y facturación</a> y <a href="{{ route('account.details') }}">editar tu contraseña y los detalles de tu cuenta</a>.</p>

    @include('account.partials.verify-notice')

    @if ($recent !== [])
        <h2 class="co-h2">Pedidos recientes</h2>
        @include('account.partials.orders-table', ['rows' => $recent])
    @endif
@endsection
