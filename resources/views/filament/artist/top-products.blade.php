{{-- «Tus productos» en «Mis ventas»: ranking con la parte de cada uno sobre el total. --}}
@php($money = fn (int $c): string => \App\Platform\Artists\ArtistSales::money($c))
<x-filament-widgets::widget>
    <x-filament::section heading="Tus productos" description="De más a menos vendido en el periodo.">
        @if ($products === [])
            <p class="cda-empty">Aún no hay ventas en este periodo. En cuanto se venda la primera pieza aparecerá aquí.</p>
        @else
            <ol class="cda-rank">
                @foreach ($products as $i => $p)
                    <li class="cda-rank__row">
                        <span class="cda-rank__pos">{{ $i + 1 }}</span>
                        <span class="cda-rank__img">@if ($p['image'])<img src="{{ $p['image'] }}" alt="" loading="lazy">@endif</span>
                        <span class="cda-rank__info">
                            <strong>{{ $p['name'] }}</strong>
                            <small>{{ $p['reference'] ? $p['reference'].' · ' : '' }}mejor día: {{ $p['best_day'] }}</small>
                            <span class="cda-bar" aria-hidden="true"><span style="width: {{ max(2, $p['share']) }}%"></span></span>
                        </span>
                        <span class="cda-rank__nums">
                            <span><b>{{ $p['units'] }}</b> ud.</span>
                            <span>{{ $money($p['base']) }}</span>
                            <span class="cda-rank__fee">{{ $money($p['commission']) }} para ti</span>
                            <span class="cda-rank__share">{{ $p['share'] }} %</span>
                        </span>
                    </li>
                @endforeach
            </ol>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
