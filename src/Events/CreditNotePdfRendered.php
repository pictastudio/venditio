<?php

namespace PictaStudio\Venditio\Events;

use Illuminate\Database\Eloquent\Model;

class CreditNotePdfRendered
{
    public function __construct(
        public readonly Model $creditNote,
    ) {}
}
