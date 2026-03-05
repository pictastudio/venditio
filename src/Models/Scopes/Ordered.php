<?php

namespace PictaStudio\Venditio\Models\Scopes;

use Illuminate\Database\Eloquent\{Builder, Model, Scope};
use PictaStudio\Venditio\Models\Scopes\Concerns\CanBeExcludedByRequest;

class Ordered implements Scope
{
    use CanBeExcludedByRequest;

    public function apply(Builder $builder, Model $model): void
    {
        if ($this->shouldExcludeScope()) {
            return;
        }

        $builder->orderBy('sort_order', 'asc');
    }
}
