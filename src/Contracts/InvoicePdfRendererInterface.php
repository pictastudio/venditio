<?php

namespace PictaStudio\Venditio\Contracts;

interface InvoicePdfRendererInterface
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function render(string $html, array $options = []): string;
}
