{{-- Aviso para confirmar el email. Variables: $waiting (pedidos de invitado con ese email). --}}
@if (! auth()->user()?->hasVerifiedEmail())
    <div class="co-alert co-verify" role="status">
        <p>
            Confirma tu email con el enlace que te hemos enviado.
            @if (($waiting ?? 0) > 0)
                Tienes {{ $waiting === 1 ? '1 pedido hecho' : $waiting.' pedidos hechos' }} sin cuenta con este email: {{ $waiting === 1 ? 'aparecerá' : 'aparecerán' }} aquí cuando lo confirmes.
            @endif
        </p>
        <form method="post" action="{{ route('account.verify.send') }}">
            @csrf
            <button class="co-link" type="submit">Reenviar el email</button>
        </form>
    </div>
@endif
