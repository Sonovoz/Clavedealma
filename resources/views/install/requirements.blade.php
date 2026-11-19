@extends('install.layout', ['step' => 1])
@section('content')
    <h1>Bienvenido a la instalación</h1>
    <p>Primero se comprueba que el servidor tiene todo lo necesario. Después se piden los datos de la base de datos y del administrador.</p>
    <ul class="checks">
        @foreach ($rows as $row)
            <li>
                <span class="dot {{ $row['ok'] ? 'yes' : ($row['required'] ? 'no' : 'opt') }}">{{ $row['ok'] ? '✓' : ($row['required'] ? '✗' : '–') }}</span>
                <span>{{ $row['label'] }}@unless ($row['ok'])<br><span class="muted">{{ $row['hint'] }}</span>@endunless</span>
            </li>
        @endforeach
    </ul>
    @if ($passes)
        <a class="btn" href="/instalar/base-de-datos">Continuar</a>
    @else
        <p class="alert err" style="margin-top:18px">Falta algo imprescindible (marcado con ✗). Arréglalo y recarga esta página.</p>
    @endif
@endsection
