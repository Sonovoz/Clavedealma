{{--
    «Mi cuenta» como WooCommerce: menú a la izquierda (Escritorio, Pedidos, Facturas,
    Devoluciones, Direcciones, Detalles de la cuenta, Salir) y la sección a la derecha.
    Es de Clave de Alma, común a todas las tiendas (un solo vendedor). Variables: $seo.
--}}
@extends('checkout.layout', ['step' => 0])

@php
    $menu = [
        ['route' => 'account.show', 'label' => 'Escritorio', 'also' => []],
        ['route' => 'account.orders', 'label' => 'Pedidos', 'also' => ['account.order']],
        ['route' => 'account.invoices', 'label' => 'Facturas', 'also' => []],
        ['route' => 'account.returns', 'label' => 'Devoluciones', 'also' => []],
        ['route' => 'account.addresses', 'label' => 'Direcciones', 'also' => ['account.address.edit']],
        ['route' => 'account.details', 'label' => 'Detalles de la cuenta', 'also' => []],
    ];
@endphp

@section('content')
    <h1 class="co-h1">Mi cuenta</h1>

    @if (session('account.status'))
        <p class="co-ok co-account__notice" role="status">{{ session('account.status') }}</p>
    @endif

    <div class="co-account">
        <nav class="co-account__nav" aria-label="Mi cuenta">
            <ul>
                @foreach ($menu as $item)
                    @php($on = request()->routeIs($item['route'], ...$item['also']))
                    <li><a href="{{ route($item['route']) }}" @if ($on) aria-current="page" @endif>{{ $item['label'] }}</a></li>
                @endforeach
                <li>
                    <form method="post" action="{{ route('account.logout') }}">
                        @csrf
                        <button type="submit">Salir</button>
                    </form>
                </li>
            </ul>
        </nav>
        <section class="co-account__content">
            @yield('account')
        </section>
    </div>
@endsection
