<?php

namespace PictaStudio\Venditio\Events;

use Illuminate\Database\Eloquent\Model;

class CreditNoteCreated
{
    public function __construct(
        public readonly Model $creditNote,
    ) {}
}
