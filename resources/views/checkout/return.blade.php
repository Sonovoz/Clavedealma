@extends('checkout.layout', ['step' => 0])

@section('content')
    <h1 class="co-h1">Devolver piezas</h1>
    <div class="co-grid">
        <form class="co-card co-form" method="post" action="{{ route('returns.store', $order->token) }}">
            @csrf
            <p>Pedido <strong>{{ $order->reference }}</strong>. Puedes pedir la devolución hasta el <strong>{{ $deadline }}</strong>. Elige qué piezas y cuántas:</p>
            @error('quantities')<p class="co-alert" role="alert">{{ $message }}</p>@enderror

            <ul class="co-lines">
                @foreach ($lines as $line)
                    <li class="co-line co-line--return">
                        <span class="co-line__img">@if ($line['image'])<img src="{{ $line['image'] }}" alt="" loading="lazy">@endif</span>
                        <div class="co-line__info">
                            <strong class="co-line__name">{{ $line['name'] }}</strong>
                            <span class="co-line__meta">{{ $line['option'] }} · {{ $line['unit'] }} / ud.</span>
                            <span class="co-line__store">{{ $line['store'] }}</span>
                            @if ($line['personalized'])
                                <span class="co-line__problem">Pieza personalizada: no admite devolución.</span>
                            @elseif ($line['max'] === 0)
                                <span class="co-line__meta">Ya está en otra devolución.</span>
                            @endif
                        </div>
                        <div class="co-line__qty">
                            <label class="co-sr" for="q-{{ $line['id'] }}">Unidades a devolver de {{ $line['name'] }}</label>
                            <select id="q-{{ $line['id'] }}" name="quantities[{{ $line['id'] }}]" @disabled($line['max'] === 0)>
                                @for ($q = 0; $q <= $line['max']; $q++)
                                    <option value="{{ $q }}" @selected((int) old('quantities.'.$line['id']) === $q)>{{ $q }}</option>
                                @endfor
                            </select>
                        </div>
                    </li>
                @endforeach
            </ul>

            <div class="co-field">
                <label for="reason">Motivo</label>
                <select id="reason" name="reason" required>
                    @foreach ($reasons as $value => $label)
                        <option value="{{ $value }}" @selected(old('reason') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('reason')<span class="co-error">{{ $message }}</span>@enderror
            </div>
            <div class="co-field">
                <label for="comment">Cuéntanos algo más (opcional)</label>
                <textarea id="comment" name="comment" rows="3" maxlength="500">{{ old('comment') }}</textarea>
            </div>

            <button class="co-btn co-btn--full" type="submit">Pedir la devolución</button>
        </form>

        <aside class="co-card co-summary">
            <h2 class="co-h2">Cómo funciona</h2>
            <ol class="co-steps-list">
                <li>Revisamos tu solicitud y te enviamos por email cómo mandarnos las piezas.</li>
                <li>Envíanos las piezas sin usar, con sus etiquetas. El envío de vuelta corre de tu cuenta, salvo que la pieza llegara con un defecto o no sea la que pediste.</li>
                <li>Al recibirlas, te devolvemos el dinero al mismo medio de pago. Si devuelves el pedido entero, también el envío que pagaste.</li>
            </ol>
            <a class="co-link" href="{{ route('checkout.thanks', $order->token) }}">Volver a mi pedido</a>
        </aside>
    </div>
@endsection
