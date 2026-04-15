<?php

namespace PictaStudio\Venditio\CreditNotes\Renderers;

use Barryvdh\DomPDF\Facade\Pdf;
use PictaStudio\Venditio\Contracts\CreditNotePdfRendererInterface;
use RuntimeException;

class DompdfCreditNotePdfRenderer implements CreditNotePdfRendererInterface
{
    public function render(string $html, array $options = []): string
    {
        if (!class_exists(Pdf::class)) {
            throw new RuntimeException('barryvdh/laravel-dompdf is required to render credit note PDFs.');
        }

        $paper = (string) ($options['paper'] ?? config('venditio.credit_notes.paper', 'a4'));
        $orientation = (string) ($options['orientation'] ?? config('venditio.credit_notes.orientation', 'portrait'));

        return Pdf::loadHTML($html)
            ->setPaper($paper, $orientation)
            ->setWarnings(false)
            ->output();
    }
}
