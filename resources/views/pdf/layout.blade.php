{{-- Plantilla común de los documentos PDF (dompdf: CSS sencillo, fuente DejaVu Sans). --}}
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>@yield('title')</title>
<style>
    @page { margin: 28mm 16mm 20mm 16mm; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 9.5pt; color: #16130f; line-height: 1.4; }
    header { position: fixed; top: -20mm; left: 0; right: 0; height: 14mm; border-bottom: 2px solid #16130f; }
    header .brand { font-size: 15pt; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; }
    header .doc { position: absolute; right: 0; top: 2mm; text-align: right; font-size: 9pt; color: #555; }
    footer { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 7.5pt; color: #777; border-top: 1px solid #ddd; padding-top: 2mm; }
    h1 { font-size: 16pt; margin: 0 0 2mm; }
    h2 { font-size: 11pt; margin: 6mm 0 2mm; padding-bottom: 1mm; border-bottom: 1px solid #ccc; }
    .muted { color: #6f675d; }
    .small { font-size: 8pt; }
    .red { color: #c8102e; font-weight: bold; }
    .tag { display: inline-block; padding: 0 4px; border: 1px solid #c8102e; color: #c8102e; font-size: 7.5pt; text-transform: uppercase; }
    table { width: 100%; border-collapse: collapse; margin-top: 1mm; }
    th { text-align: left; font-size: 7.5pt; text-transform: uppercase; letter-spacing: .5px; color: #555; border-bottom: 1.5px solid #16130f; padding: 1.5mm 1.5mm; }
    td { border-bottom: 1px solid #e4e1dc; padding: 1.8mm 1.5mm; vertical-align: top; }
    .num { text-align: right; white-space: nowrap; }
    .check { width: 8mm; text-align: center; }
    .box { display: inline-block; width: 3.5mm; height: 3.5mm; border: 1px solid #16130f; }
    .block { page-break-inside: avoid; margin-bottom: 5mm; }
    .kpis { width: 100%; margin: 3mm 0; }
    .kpis td { border: 1px solid #e4e1dc; padding: 3mm; width: 25%; }
    .kpis .v { font-size: 13pt; font-weight: bold; }
    .grid td { border: 0; padding: 0 4mm 0 0; }
</style>
</head>
<body>
<header>
    <div class="brand">Clave de Alma</div>
    <div class="doc">@yield('title')<br>{{ $generatedAt->format('d/m/Y H:i') }}</div>
</header>
<footer>
    Clave de Alma · @yield('footer', 'Documento interno')
    <script type="text/php">
        if (isset($pdf)) { $pdf->page_text($pdf->get_width() - 92, $pdf->get_height() - 30, "Página {PAGE_NUM} de {PAGE_COUNT}", null, 7, [0.45, 0.45, 0.45]); }
    </script>
</footer>
<main>
    @yield('content')
</main>
</body>
</html>
