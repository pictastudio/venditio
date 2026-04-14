<?php

namespace PictaStudio\Venditio\Events;

use Illuminate\Database\Eloquent\Model;

class InvoiceCreated
{
    public function __construct(
        public readonly Model $invoice,
    ) {}
}
