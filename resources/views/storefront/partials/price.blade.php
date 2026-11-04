{{-- Precio con IVA; si hay rebaja, el precio anterior es el más bajo de los 30 días previos (lo calcula el sistema). --}}
@if ($discount)
    <span class="sf-price sf-price--sale"><b>{{ $price }}</b><s>{{ $discount['before'] }}</s><em>−{{ $discount['percent'] }} %</em></span>
@else
    <span class="sf-price"><b>{{ $price }}</b></span>
@endif
