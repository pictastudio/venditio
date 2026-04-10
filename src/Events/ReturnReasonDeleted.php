<?php

namespace PictaStudio\Venditio\Events;

use Illuminate\Database\Eloquent\Model;

class ReturnReasonDeleted
{
    public function __construct(
        public readonly Model $returnReason,
    ) {}
}
