<x-mail::message>
@if ($forTeam)
# Aviso de {{ $notice->artist->display_name }}

**{{ $notice->title }}**@if ($notice->event_date) · {{ $notice->event_date->translatedFormat('j \d\e F') }}@endif


{{ $notice->message }}

@if (($notice->attachments ?? []) !== [])
Lleva {{ count($notice->attachments) }} {{ count($notice->attachments) === 1 ? 'archivo' : 'archivos' }}: los verás en el panel.
@endif

<x-mail::button :url="url('/admin/avisos/'.$notice->id)">
Abrir el aviso
</x-mail::button>
@else
# Tu aviso está {{ mb_strtolower($notice->status->label()) }}

**{{ $notice->title }}**

@if ($notice->reply)
{{ $notice->reply }}
@else
El equipo de Clave de Alma ya se está ocupando.
@endif

<x-mail::button :url="url('/admin/avisos/'.$notice->id)">
Ver el aviso
</x-mail::button>
@endif
</x-mail::message>
