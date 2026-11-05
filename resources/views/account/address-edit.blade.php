@extends('account.layout')

@section('account')
    @php($v = fn (string $k, ?string $default = null) => old($k, $address?->{$k} ?? $default))
    <form class="co-form" method="post" action="{{ route('account.address.save') }}" novalidate>
        @csrf
        <h2 class="co-h2">Dirección de facturación y envío</h2>
        @include('account.partials.input', ['name' => 'name', 'label' => 'Nombre y apellidos', 'autocomplete' => 'name', 'value' => $v('name', $defaultName)])
        <div class="co-field">
            <label for="country">País / Región</label>
            <select id="country" name="country" autocomplete="country">
                @foreach ($countries as $code => $label)
                    <option value="{{ $code }}" @selected($v('country', 'ES') === $code)>{{ $label }}</option>
                @endforeach
            </select>
            @error('country')<span class="co-error">{{ $message }}</span>@enderror
        </div>
        @include('account.partials.input', ['name' => 'address_line1', 'label' => 'Dirección de la calle', 'autocomplete' => 'address-line1', 'value' => $v('address_line1'), 'placeholder' => 'Nombre de la calle y número de la casa'])
        @include('account.partials.input', ['name' => 'address_line2', 'label' => 'Apartamento, habitación, etc. (opcional)', 'autocomplete' => 'address-line2', 'value' => $v('address_line2'), 'required' => false])
        <div class="co-row">
            @include('account.partials.input', ['name' => 'postal_code', 'label' => 'Código postal', 'autocomplete' => 'postal-code', 'value' => $v('postal_code'), 'inputmode' => 'numeric'])
            @include('account.partials.input', ['name' => 'city', 'label' => 'Localidad / Ciudad', 'autocomplete' => 'address-level2', 'value' => $v('city')])
        </div>
        @include('account.partials.input', ['name' => 'province', 'label' => 'Región / Provincia (opcional)', 'autocomplete' => 'address-level1', 'value' => $v('province'), 'required' => false])
        @include('account.partials.input', ['name' => 'phone', 'label' => 'Teléfono', 'type' => 'tel', 'autocomplete' => 'tel', 'value' => $v('phone')])
        <button class="co-btn" type="submit">Guardar dirección</button>
    </form>
@endsection
