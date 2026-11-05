{{-- «Día a día» en «Mis ventas». --}}
@php($money = fn (int $c): string => \App\Platform\Artists\ArtistSales::money($c))
<x-filament-widgets::widget>
    <x-filament::section heading="Día a día" description="Solo los días con ventas, del más reciente al más antiguo." collapsible>
        @if ($days === [])
            <p class="cda-empty">Sin ventas en este periodo.</p>
        @else
            <div class="cda-table-wrap">
                <table class="cda-table">
                    <thead><tr><th>Día</th><th>Unidades</th><th>Ventas (sin IVA)</th><th>Tu comisión</th></tr></thead>
                    <tbody>
                        @foreach ($days as $d)
                            <tr>
                                <td>{{ \Carbon\CarbonImmutable::parse($d['date'])->translatedFormat('D j M') }}</td>
                                <td>{{ $d['units'] }}</td>
                                <td>{{ $money($d['base']) }}</td>
                                <td>{{ $money($d['commission']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr><th>Total</th><th>{{ $totals['units'] }}</th><th>{{ $money($totals['base']) }}</th><th>{{ $money($totals['commission']) }}</th></tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
