<?php

namespace PictaStudio\Venditio\Contracts;

use Illuminate\Database\Eloquent\Model;

interface InvoiceNumberGeneratorInterface
{
    /**
     * Generate an identifier for the invoice.
     */
    public function generate(Model $invoice): string;
}
