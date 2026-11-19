@extends('install.layout', ['step' => 0, 'title' => 'Acceso recuperado'])
@section('content')
    <h1>Acceso recuperado</h1>
    <p>Ya puedes entrar con <strong>{{ $email }}</strong> y la contraseña nueva. Te pedirá configurar la verificación en dos pasos con una app del móvil.</p>
    <p><a class="btn" href="{{ url('/admin') }}">Entrar al panel</a></p>
    <p class="muted">La clave de recuperación ya no sirve. Si en el <code>.env</code> sigue la línea <code>RECUPERAR_ACCESO=</code> con algo escrito, déjala vacía.</p>
@endsection
