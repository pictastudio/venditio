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

        $groupingColumns = [];

        if (method_exists($model, 'getParentKeyName')) {
            $groupingColumns[] = $model->getParentKeyName();
        }

        if (method_exists($model, 'getOrderingGroupKeyNames')) {
            $groupingColumns = [
                ...$groupingColumns,
                ...$model->getOrderingGroupKeyNames(),
            ];
        }

        foreach (array_values(array_unique($groupingColumns)) as $column) {
            if (!is_string($column) || $column === '') {
                continue;
            }

            $builder->orderBy($model->qualifyColumn($column), 'asc');
        }

        $builder
            ->orderBy($model->qualifyColumn('sort_order'), 'asc')
            ->orderBy($model->getQualifiedKeyName(), 'asc');
    }
}
