<?php

namespace PictaStudio\Venditio\Contracts;

interface InvoiceTemplateInterface
{
    public function key(): string;

    public function version(): ?string;

    /**
     * @param  array<string, mixed>  $invoice
     */
    public function render(array $invoice): string;
}
