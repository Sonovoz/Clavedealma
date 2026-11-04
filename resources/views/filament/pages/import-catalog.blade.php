@php
    $row = 'display:flex;justify-content:space-between;gap:1rem;padding:.4rem 0;border-bottom:1px solid rgba(127,127,127,.15)';
    $cell = 'padding:.35rem .6rem;border-bottom:1px solid rgba(127,127,127,.15);text-align:left;vertical-align:top';
@endphp

<x-filament-panels::page>
    <x-filament::section heading="Protocolo" description="Cómo preparar los artículos.">
        <ol style="list-style:decimal;padding-left:1.25rem;line-height:1.7">
            <li>Descarga la plantilla y rellena una fila por variante (talla × color). La pestaña «Instrucciones» explica cada columna.</li>
            <li>Nombra las fotos <code>MODELO_NN.jpg</code> o <code>MODELO_COLOR_NN.jpg</code> y mételas en un ZIP.</li>
            <li>Sube los dos ficheros y pulsa <strong>Comprobar</strong>. Corrige los errores que salgan.</li>
            <li>Pulsa <strong>Importar</strong>. Se puede repetir con la hoja corregida: actualiza, no duplica.</li>
        </ol>
    </x-filament::section>

    <x-filament::section>
        {{ $this->form }}
    </x-filament::section>

    @if ($result !== null)
        <x-filament::section :heading="$result['applied'] ? 'Resultado de la importación' : 'Resultado de la comprobación'">
            <dl>
                @foreach ($result['summary'] as $label => $value)
                    <div style="{{ $row }}"><dt>{{ $label }}</dt><dd><strong>{{ $value }}</strong></dd></div>
                @endforeach
            </dl>
        </x-filament::section>

        @foreach (['errors' => ['Errores', 'rgb(220,38,38)', 'Error'], 'warnings' => ['Avisos', 'rgb(217,119,6)', 'Aviso']] as $key => [$heading, $color, $column])
            @if ($result[$key] !== [])
                <x-filament::section :heading="$heading.' ('.count($result[$key]).')'">
                    <div style="overflow-x:auto">
                        <table style="width:100%;border-collapse:collapse;font-size:.875rem">
                            <thead><tr><th style="{{ $cell }}">Fila</th><th style="{{ $cell }}">Campo</th><th style="{{ $cell }}">{{ $column }}</th></tr></thead>
                            <tbody>
                                @foreach ($result[$key] as $item)
                                    <tr>
                                        <td style="{{ $cell }};white-space:nowrap">{{ $item['fila'] ?? '—' }}</td>
                                        <td style="{{ $cell }};white-space:nowrap"><code>{{ $item['campo'] }}</code></td>
                                        <td style="{{ $cell }};color:{{ $color }}">{{ $item['mensaje'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-filament::section>
            @endif
        @endforeach
    @endif
</x-filament-panels::page>
