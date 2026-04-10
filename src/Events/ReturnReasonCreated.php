<?php

namespace PictaStudio\Venditio\Events;

use Illuminate\Database\Eloquent\Model;

class ReturnReasonCreated
{
    public function __construct(
        public readonly Model $returnReason,
    ) {}
}
