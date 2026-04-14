<?php

namespace PictaStudio\Venditio\Events;

use Illuminate\Database\Eloquent\Model;

class InvoicePdfRendered
{
    public function __construct(
        public readonly Model $invoice,
    ) {}
}
