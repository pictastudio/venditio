<?php

namespace PictaStudio\Venditio\Contracts;

interface CreditNoteTemplateInterface
{
    public function key(): string;

    public function version(): ?string;

    /**
     * @param  array<string, mixed>  $creditNote
     */
    public function render(array $creditNote): string;
}
