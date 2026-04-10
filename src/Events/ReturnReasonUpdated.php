<?php

namespace PictaStudio\Venditio\Events;

use Illuminate\Database\Eloquent\Model;

class ReturnReasonUpdated
{
    public function __construct(
        public readonly Model $returnReason,
    ) {}
}
