@extends('checkout.layout', ['step' => 0])

{{-- «¿Has olvidado tu contraseña?» de WooCommerce. --}}
@section('content')
    <h1 class="co-h1">Mi cuenta</h1>
    <form class="co-card co-form co-narrow" method="post" action="{{ route('account.password.email') }}" novalidate>
        @csrf
        @if (session('account.status'))
            <p class="co-ok" role="status">{{ session('account.status') }}</p>
        @endif
        <p>¿Has olvidado tu contraseña? Escribe tu correo electrónico y te enviaremos un enlace para crear una nueva.</p>
        @include('checkout.partials.field', ['name' => 'email', 'label' => 'Correo electrónico', 'type' => 'email', 'autocomplete' => 'email'])
        <button class="co-btn co-btn--full" type="submit">Restablecer contraseña</button>
        <a class="co-link" href="{{ route('account.show') }}">← Volver a acceder</a>
    </form>
@endsection
