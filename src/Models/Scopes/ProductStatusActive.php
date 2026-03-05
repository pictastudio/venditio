<?php

namespace PictaStudio\Venditio\Models\Scopes;

use Illuminate\Database\Eloquent\{Builder, Model, Scope};
use PictaStudio\Venditio\Models\Scopes\Concerns\CanBeExcludedByRequest;

class ProductStatusActive implements Scope
{
    use CanBeExcludedByRequest;

    public function apply(Builder $builder, Model $model): void
    {
        if ($this->shouldExcludeScope()) {
            return;
        }

        if (request()->routeIs(config('venditio.scopes.routes_to_exclude'))) {
            return;
        }

        if (!method_exists($model, 'scopeActiveStatuses')) {
            return;
        }

        $model->scopeActiveStatuses($builder);
    }
}
