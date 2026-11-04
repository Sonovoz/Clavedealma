<?php

namespace App\Platform\Pdf;

use Barryvdh\DomPDF\Facade\Pdf;

class DompdfRenderer implements PdfRenderer
{
    public function render(string $view, array $data, string $orientation = 'portrait'): string
    {
        return Pdf::loadView($view, $data)
            ->setPaper('a4', $orientation)
            ->setOption(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false, 'isPhpEnabled' => true])
            ->output();
    }
}
