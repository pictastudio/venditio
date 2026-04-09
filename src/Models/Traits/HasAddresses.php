<?php

namespace PictaStudio\Venditio\Models\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

trait HasAddresses
{
    public function addresses(): MorphMany
    {
        return $this->morphMany(resolve_model('address'), 'addressable');
    }
}
