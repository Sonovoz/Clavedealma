@php($required = $required ?? true)
<div class="co-field">
    <label for="{{ $name }}">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type ?? 'text' }}" value="{{ old($name) }}"
        autocomplete="{{ $autocomplete ?? 'off' }}" @if (isset($inputmode)) inputmode="{{ $inputmode }}" @endif
        @if (isset($placeholder)) placeholder="{{ $placeholder }}" @endif @required($required)
        @if (! empty($quote)) data-quote-input @endif @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror>
    @if (isset($hint))<span class="co-hint">{{ $hint }}</span>@endif
    @error($name)<span class="co-error" id="{{ $name }}-error">{{ $message }}</span>@enderror
</div>
