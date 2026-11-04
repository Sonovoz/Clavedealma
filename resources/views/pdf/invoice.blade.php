@extends('pdf.layout')
@section('title', $invoice->type->label().' '.$invoice->code)
@section('footer', ($issuer['legal_name'] ?? 'Clave de Alma').' · NIF '.($issuer['nif'] ?? '').' · '.$invoice->code)

@section('content')
    {{-- Veri*Factu: QR (30-40 mm) y leyenda al principio de la factura (Orden HAC/1177/2024).
         Solo si la factura se emitió con Veri*Factu activado. --}}
    @if ($invoice->verifactu)
    <table class="grid" style="margin-bottom:4mm">
        <tr>
            <td style="width:34mm;vertical-align:top"><img src="{{ $qr }}" alt="QR" style="width:32mm;height:32mm"></td>
            <td style="vertical-align:middle">
                <p style="margin:0;font-size:11pt"><strong>VERI*FACTU</strong></p>
                <p class="small muted" style="margin:1mm 0 0">Factura verificable en la sede electrónica de la AEAT.</p>
            </td>
        </tr>
    </table>
    @endif

    <table class="grid">
        <tr>
            <td style="width:58%">
                <h1>{{ $invoice->type->label() }}</h1>
                <p>Número <strong>{{ $invoice->code }}</strong><br>
                    Fecha de expedición {{ $date }}<br>
                    Pedido {{ $invoice->order_reference }}
                    @if ($invoice->rectifies)<br>Rectifica la factura <strong>{{ $invoice->rectifies->code }}</strong>@endif
                    @if ($invoice->reason)<br>Motivo: {{ $invoice->reason }}@endif
                </p>
            </td>
            <td style="width:42%">
                <p class="small muted" style="margin:0">EMISOR</p>
                <p style="margin:1mm 0 0"><strong>{{ $issuer['legal_name'] }}</strong><br>
                    NIF {{ $issuer['nif'] }}<br>
                    {{ $issuer['address'] }}<br>
                    {{ $issuer['postal_code'] }} {{ $issuer['city'] }}@if ($issuer['province']) ({{ $issuer['province'] }})@endif
                    @if ($issuer['email'])<br>{{ $issuer['email'] }}@endif</p>
                @if ($invoice->customer_name)
                    <p class="small muted" style="margin:3mm 0 0">CLIENTE</p>
                    <p style="margin:1mm 0 0">{{ $invoice->customer_name }}</p>
                @endif
            </td>
        </tr>
    </table>

    <h2>Detalle</h2>
    <table>
        <thead><tr><th>Concepto</th><th class="num">Ud.</th><th class="num">Precio (IVA incl.)</th><th class="num">IVA</th><th class="num">Importe</th></tr></thead>
        <tbody>
            @foreach ($lines as $l)
                <tr><td>{{ $l['description'] }}</td><td class="num">{{ $l['quantity'] }}</td><td class="num">{{ $l['unit'] }}</td><td class="num">{{ $l['rate'] }}</td><td class="num">{{ $l['total'] }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <table class="grid" style="margin-top:5mm">
        <tr>
            <td style="width:55%"></td>
            <td style="width:45%;vertical-align:top">
                <table>
                    <thead><tr><th>Tipo IVA</th><th class="num">Base imponible</th><th class="num">Cuota</th></tr></thead>
                    <tbody>@foreach ($vatBreakdown as $v)<tr><td>{{ $v['rate'] }} %</td><td class="num">{{ $v['base'] }}</td><td class="num">{{ $v['vat'] }}</td></tr>@endforeach</tbody>
                </table>
                <table style="margin-top:2mm">
                    <tbody>
                        <tr><td>Base imponible</td><td class="num">{{ $base }}</td></tr>
                        <tr><td>IVA</td><td class="num">{{ $vatTotal }}</td></tr>
                        <tr><td><strong>Total</strong></td><td class="num"><strong>{{ $total }}</strong></td></tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </table>
@endsection
