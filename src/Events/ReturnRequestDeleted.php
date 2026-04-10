<?php

namespace PictaStudio\Venditio\Events;

use Illuminate\Database\Eloquent\Model;

class ReturnRequestDeleted
{
    public function __construct(
        public readonly Model $returnRequest,
    ) {}
}
