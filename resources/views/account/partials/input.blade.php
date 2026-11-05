{{-- Campo de «Mi cuenta» con valor inicial (el de la compra usa checkout.partials.field). --}}
@php($required = $required ?? true)
<div class="co-field">
    <label for="{{ $name }}">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type ?? 'text' }}" value="{{ $value ?? '' }}"
        autocomplete="{{ $autocomplete ?? 'off' }}" @if (isset($inputmode)) inputmode="{{ $inputmode }}" @endif
        @if (isset($placeholder)) placeholder="{{ $placeholder }}" @endif @required($required)
        @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror>
    @if (isset($hint))<span class="co-hint">{{ $hint }}</span>@endif
    @error($name)<span class="co-error" id="{{ $name }}-error">{{ $message }}</span>@enderror
</div>
