@extends('checkout.layout', ['step' => 0])

@section('content')
    <article class="co-card co-legal">
        @if ($doc === null)
            <h1 class="co-h1">{{ $title }}</h1>
            <p class="co-alert">Texto pendiente: se genera al completar los datos fiscales de Clave de Alma.</p>
        @else
            @if (! $doc->approved_at)
                <p class="co-alert">Borrador pendiente de revisión por la asesoría legal.</p>
            @endif
            <div class="co-legal__body">{!! $html !!}</div>
            <p class="co-fine">Versión {{ $doc->version }} · {{ $doc->generated_at->timezone('Europe/Madrid')->format('d/m/Y') }}</p>
        @endif
    </article>
@endsection
