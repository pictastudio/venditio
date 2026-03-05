<?php

namespace PictaStudio\Venditio\Models\Scopes;

use Illuminate\Database\Eloquent\{Builder, Model, Scope};
use PictaStudio\Venditio\Models\Scopes\Concerns\CanBeExcludedByRequest;

class InDateRange implements Scope
{
    use CanBeExcludedByRequest;

    public function __construct(
        private ?string $startColumn = null,
        private ?string $endColumn = null,
        private ?bool $includeStartDate = null,
        private ?bool $includeEndDate = null,
        private ?bool $allowNull = null
    ) {
        $this->startColumn ??= 'starts_at';
        $this->endColumn ??= 'ends_at';

        $this->includeStartDate ??= config('venditio.scopes.in_date_range.include_start_date', true);
        $this->includeEndDate ??= config('venditio.scopes.in_date_range.include_end_date', true);

        $this->allowNull ??= config('venditio.scopes.in_date_range.allow_null', true);
    }

    public function apply(Builder $builder, Model $model): void
    {
        if ($this->shouldExcludeScope('exclude_date_range_scope')) {
            return;
        }

        if (request()->routeIs(config('venditio.scopes.routes_to_exclude'))) {
            return;
        }

        $builder->where(function (Builder $query) {
            $this->buildQuery($query, $this->startColumn, $this->includeStartDate ? '<=' : '<');
            $this->buildQuery($query, $this->endColumn, $this->includeEndDate ? '>=' : '>');
        });
    }

    public function buildQuery(Builder $query, string $column, string $operator): Builder
    {
        return $query->where(fn (Builder $query) => (
            $query->where(fn (Builder $subQuery) => (
                $subQuery->when(
                    $this->allowNull,
                    fn (Builder $subQuery) => $subQuery->whereNotNull($column)
                )
                    ->where(
                        $column,
                        $operator,
                        now()
                    )
            ))
                ->when(
                    $this->allowNull,
                    fn (Builder $subQuery) => $subQuery->orWhere(fn (Builder $subQuery) => (
                        $subQuery->whereNull($column)
                    ))
                )
        ));
    }

    // public function extend(Builder $builder): void
    // {
    //     $builder->macro('inDateRange', function (Builder $builder, $startColumn, $endColumn, $includeStartDate = null, $includeEndDate = null) {
    //         $builder->withoutGlobalScope($this);
    //         return $builder->withGlobalScope($this, new static(
    //             $startColumn,
    //             $endColumn,
    //             $includeStartDate,
    //             $includeEndDate
    //         ));
    //     });
    // }
}
