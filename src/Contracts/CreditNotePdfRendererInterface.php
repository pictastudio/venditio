<?php

namespace PictaStudio\Venditio\Contracts;

interface CreditNotePdfRendererInterface
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function render(string $html, array $options = []): string;
}
