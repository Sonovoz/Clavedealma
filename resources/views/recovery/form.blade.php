@extends('install.layout', ['step' => 0, 'title' => 'Recuperar el acceso'])
@section('content')
    <h1>Recuperar el acceso al panel</h1>
    <p>Escribe la clave que has puesto en <code>RECUPERAR_ACCESO</code> del <code>.env</code>, tu email y una contraseña nueva. Si el email no existe, se crea un superadministrador con él.</p>
    <form method="post" action="/recuperar-acceso">
        @csrf
        <label for="key">Clave de recuperación (la del .env)</label>
        <input id="key" name="key" type="password" required autocomplete="off">
        @error('key')<div class="error">{{ $message }}</div>@enderror
        <label for="email">Email (el usuario para entrar)</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email">
        @error('email')<div class="error">{{ $message }}</div>@enderror
        <label for="password">Contraseña nueva (12 caracteres o más)</label>
        <input id="password" name="password" type="password" required autocomplete="new-password" minlength="12">
        @error('password')<div class="error">{{ $message }}</div>@enderror
        <label for="password_confirmation">Repite la contraseña</label>
        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" minlength="12">
        <p class="muted" style="margin-top:10px">La clave solo sirve una vez. Al entrar, el panel te pedirá configurar de nuevo la verificación en dos pasos.</p>
        <button class="btn" type="submit">Poner la contraseña nueva</button>
    </form>
@endsection
