@extends('install.layout', ['step' => 3])
@section('content')
    <h1>Sitio y administrador</h1>
    @if (session('install.db'))<p class="alert ok" role="status">{{ session('install.db') }}</p>@endif
    @error('install')<p class="alert err" role="alert">{{ $message }}</p>@enderror
    <p>La dirección de la web y tu cuenta de superadministrador para entrar al panel.</p>
    <form method="post" action="/instalar/administrador">
        @csrf
        <label for="url">Dirección de la web</label>
        <input id="url" name="url" value="{{ old('url', $url) }}" required>
        @error('url')<div class="error">{{ $message }}</div>@enderror
        <label for="name">Tu nombre</label>
        <input id="name" name="name" value="{{ old('name') }}" required autocomplete="name">
        @error('name')<div class="error">{{ $message }}</div>@enderror
        <label for="email">Email (el usuario para entrar)</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email">
        @error('email')<div class="error">{{ $message }}</div>@enderror
        <label for="password">Contraseña (12 caracteres o más)</label>
        <input id="password" name="password" type="password" required autocomplete="new-password" minlength="12">
        @error('password')<div class="error">{{ $message }}</div>@enderror
        <label for="password_confirmation">Repite la contraseña</label>
        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" minlength="12">
        @if ($sample)
            <label style="display:flex;gap:8px;align-items:flex-start;font-weight:normal;margin-top:14px">
                <input type="checkbox" name="sample_store" value="1" style="width:auto;margin-top:3px" @checked(old('sample_store', true))>
                <span>Crear la tienda de ejemplo <strong>Farruquito</strong>, publicada, con precios de ejemplo (solo mientras la web está en pruebas). Se oculta en el panel: <em>Tiendas → Farruquito → Estado</em>.</span>
            </label>
        @endif
        <p class="muted" style="margin-top:10px">Al pulsar «Instalar» se crean las tablas, los roles y tu cuenta. Puede tardar un minuto.</p>
        <button class="btn" type="submit">Instalar</button>
    </form>
@endsection
