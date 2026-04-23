<?php

namespace PictaStudio\Venditio\Models\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use PictaStudio\Venditio\Models\Scopes\Active;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

trait HasDiscounts
{
    public function discounts(): MorphMany
    {
        return $this->morphMany(resolve_model('discount'), 'discountable')
            ->withoutGlobalScope(Active::class);
    }

    public function validDiscounts(): MorphMany
    {
        return $this->discounts()
            ->where('active', true)
            ->where('starts_at', '<=', now())
            ->where(fn (Builder $query) => $query
                ->whereNull('ends_at')
                ->orWhere('ends_at', '>=', now()));
    }

    public function expiredDiscounts(): MorphMany
    {
        return $this->discounts()
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', now());
    }
}
