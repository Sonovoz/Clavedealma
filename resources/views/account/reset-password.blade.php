@extends('checkout.layout', ['step' => 0])

@section('content')
    <h1 class="co-h1">Mi cuenta</h1>
    <form class="co-card co-form co-narrow" method="post" action="{{ route('account.password.update') }}" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <p>Escribe tu contraseña nueva.</p>
        @include('account.partials.input', ['name' => 'email', 'label' => 'Correo electrónico', 'type' => 'email', 'autocomplete' => 'email', 'value' => old('email', $email)])
        @include('account.partials.input', ['name' => 'password', 'label' => 'Contraseña nueva', 'type' => 'password', 'autocomplete' => 'new-password', 'hint' => 'Mínimo 8 caracteres.'])
        @include('account.partials.input', ['name' => 'password_confirmation', 'label' => 'Repite la contraseña nueva', 'type' => 'password', 'autocomplete' => 'new-password'])
        <button class="co-btn co-btn--full" type="submit">Guardar</button>
    </form>
@endsection
