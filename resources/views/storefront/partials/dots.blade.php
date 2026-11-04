@if (count($colors) > 1)
    <span class="sf-dots" aria-label="{{ count($colors) }} colores: {{ implode(', ', array_column($colors, 'name')) }}">
        @foreach ($colors as $color)
            <span title="{{ $color['name'] }}" style="{{ $color['swatch'] ? "background-image:url('{$color['swatch']}')" : 'background:'.($color['hex'] ?? '#999') }}"></span>
        @endforeach
    </span>
@endif
