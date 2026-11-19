@extends('install.layout', ['step' => 2])
@section('content')
    <h1>Base de datos</h1>
    <p>Los datos de la base de datos MariaDB que habéis creado para la web (por ejemplo en phpMyAdmin). Si MariaDB está en el contenedor <code>mariadb</code>, el servidor es <code>mariadb</code>.</p>
    @error('database')<p class="alert err" role="alert">{{ $message }}</p>@enderror
    <form method="post" action="/instalar/base-de-datos">
        @csrf
        <div class="row">
            <div>
                <label for="host">Servidor</label>
                <input id="host" name="host" value="{{ old('host', $host) }}" required autocomplete="off">
                @error('host')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="port">Puerto</label>
                <input id="port" name="port" value="{{ old('port', $port) }}" required inputmode="numeric">
                @error('port')<div class="error">{{ $message }}</div>@enderror
            </div>
        </div>
        <label for="database">Nombre de la base de datos</label>
        <input id="database" name="database" value="{{ old('database', $database) }}" required autocomplete="off" placeholder="clavedealma">
        <label for="username">Usuario</label>
        <input id="username" name="username" value="{{ old('username', $username) }}" required autocomplete="off">
        @error('username')<div class="error">{{ $message }}</div>@enderror
        <label for="password">Contraseña</label>
        <input id="password" name="password" type="password" autocomplete="new-password">
        <p class="muted" style="margin-top:6px">Se guarda solo en el archivo <code>.env</code> del servidor.</p>
        <button class="btn" type="submit">Probar y guardar</button>
    </form>
@endsection
