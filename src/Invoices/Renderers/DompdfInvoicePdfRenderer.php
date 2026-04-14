<?php

namespace PictaStudio\Venditio\Invoices\Renderers;

use Barryvdh\DomPDF\Facade\Pdf;
use PictaStudio\Venditio\Contracts\InvoicePdfRendererInterface;
use RuntimeException;

class DompdfInvoicePdfRenderer implements InvoicePdfRendererInterface
{
    public function render(string $html, array $options = []): string
    {
        if (!class_exists(Pdf::class)) {
            throw new RuntimeException('barryvdh/laravel-dompdf is required to render invoice PDFs.');
        }

        $paper = (string) ($options['paper'] ?? config('venditio.invoices.paper', 'a4'));
        $orientation = (string) ($options['orientation'] ?? config('venditio.invoices.orientation', 'portrait'));

        return Pdf::loadHTML($html)
            ->setPaper($paper, $orientation)
            ->setWarnings(false)
            ->output();
    }
}
