@extends('account.layout')

{{-- «Detalles de la cuenta» de WooCommerce, con «Cambio de contraseña». --}}
@section('account')
    <form class="co-form" method="post" action="{{ route('account.details.save') }}" novalidate>
        @csrf
        @include('account.partials.input', ['name' => 'name', 'label' => 'Nombre', 'autocomplete' => 'name', 'value' => old('name', $user->name), 'hint' => 'Así te saludamos en «Mi cuenta» y en los emails.'])
        @include('account.partials.input', ['name' => 'email', 'label' => 'Dirección de correo electrónico', 'type' => 'email', 'autocomplete' => 'email', 'value' => old('email', $user->email)])

        <fieldset>
            <legend class="co-h2">Cambio de contraseña</legend>
            @include('account.partials.input', ['name' => 'current_password', 'label' => 'Contraseña actual (déjalo en blanco para no cambiarla)', 'type' => 'password', 'autocomplete' => 'current-password', 'required' => false, 'hint' => 'También hace falta para cambiar el email.'])
            @include('account.partials.input', ['name' => 'password', 'label' => 'Nueva contraseña (déjalo en blanco para no cambiarla)', 'type' => 'password', 'autocomplete' => 'new-password', 'required' => false])
            @include('account.partials.input', ['name' => 'password_confirmation', 'label' => 'Confirmar la nueva contraseña', 'type' => 'password', 'autocomplete' => 'new-password', 'required' => false])
        </fieldset>
        <button class="co-btn" type="submit">Guardar los cambios</button>
    </form>
@endsection
