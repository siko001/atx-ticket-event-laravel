<?php

namespace AtxDigital\Ticketing\Services;

use AtxDigital\Ticketing\Contracts\PdfGeneratorContract;
use Barryvdh\DomPDF\PDF;

/**
 * Default PDF implementation. Dompdf is pure PHP (no Chromium/Node runtime),
 * so tickets render on any hosting. Swap the PdfGeneratorContract binding for
 * a Browsershot-backed implementation if you need richer CSS.
 */
class DompdfGenerator implements PdfGeneratorContract
{
    public function generate(string $view, array $data = []): string
    {
        /** @var PDF $pdf */
        $pdf = app('dompdf.wrapper');

        return $pdf->loadView($view, $data)->setPaper('a4')->output();
    }
}
