<?php

namespace PictaStudio\Venditio\Contracts;

use Illuminate\Database\Eloquent\Model;

interface CreditNoteNumberGeneratorInterface
{
    /**
     * Generate an identifier for the credit note.
     */
    public function generate(Model $creditNote): string;
}
