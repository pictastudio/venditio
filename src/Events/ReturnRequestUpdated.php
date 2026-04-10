<?php

namespace PictaStudio\Venditio\Events;

use Illuminate\Database\Eloquent\Model;

class ReturnRequestUpdated
{
    public function __construct(
        public readonly Model $returnRequest,
    ) {}
}
