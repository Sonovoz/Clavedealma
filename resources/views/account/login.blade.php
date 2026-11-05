{{-- «Mi cuenta» sin sesión, como WooCommerce: «Acceder» y «Registrarse» lado a lado. --}}
@extends('checkout.layout', ['step' => 0])

@section('content')
    <h1 class="co-h1">Mi cuenta</h1>

    @if (session('account.status'))
        <p class="co-ok co-account__notice" role="status">{{ session('account.status') }}</p>
    @endif

    <div class="co-two">
        <form class="co-card co-form" method="post" action="{{ route('account.login') }}" novalidate>
            @csrf
            <h2 class="co-h2">Acceder</h2>
            @include('checkout.partials.field', ['name' => 'login_email', 'label' => 'Correo electrónico', 'type' => 'email', 'autocomplete' => 'email'])
            <div class="co-field">
                <label for="login_password">Contraseña</label>
                <input id="login_password" name="login_password" type="password" autocomplete="current-password" required>
            </div>
            <label class="co-check"><input type="checkbox" name="remember" value="1"><span>Recuérdame</span></label>
            <button class="co-btn co-btn--full" type="submit">Acceder</button>
            <a class="co-link" href="{{ route('account.password.request') }}">¿Has olvidado tu contraseña?</a>
        </form>

        <form class="co-card co-form" method="post" action="{{ route('account.register') }}" novalidate>
            @csrf
            <h2 class="co-h2">Registrarse</h2>
            @include('checkout.partials.field', ['name' => 'reg_email', 'label' => 'Correo electrónico', 'type' => 'email', 'autocomplete' => 'email'])
            <div class="co-field">
                <label for="reg_password">Contraseña</label>
                <input id="reg_password" name="reg_password" type="password" autocomplete="new-password" minlength="8" required @error('reg_password') aria-invalid="true" @enderror>
                <span class="co-hint">Mínimo 8 caracteres.</span>
                @error('reg_password')<span class="co-error">{{ $message }}</span>@enderror
            </div>
            <p class="co-fine">Tus datos personales se usarán para gestionar el acceso a tu cuenta y tus pedidos, y para lo que explica nuestra <a href="{{ route('legal', 'privacidad') }}" target="_blank">política de privacidad</a>.</p>
            <button class="co-btn co-btn--full" type="submit">Registrarse</button>
            <p class="co-fine">No hace falta cuenta para comprar: también puedes hacerlo como invitado.</p>
        </form>
    </div>
@endsection
