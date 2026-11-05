@extends('account.layout')

@section('account')
    @include('account.partials.verify-notice')

    @if ($rows === [])
        <div class="co-notice">
            <p>Todavía no has hecho ningún pedido.</p>
            <a class="co-btn" href="{{ route('market') }}">Explorar productos</a>
        </div>
    @else
        @include('account.partials.orders-table', ['rows' => $rows])
        @if ($orders->hasPages())
            <nav class="co-pager" aria-label="Páginas de pedidos">
                @if ($orders->previousPageUrl())<a class="co-btn co-btn--ghost co-btn--small" href="{{ $orders->previousPageUrl() }}">Anterior</a>@endif
                @if ($orders->nextPageUrl())<a class="co-btn co-btn--ghost co-btn--small" href="{{ $orders->nextPageUrl() }}">Siguiente</a>@endif
            </nav>
        @endif
    @endif
@endsection
