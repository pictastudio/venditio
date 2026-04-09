<?php

namespace PictaStudio\Venditio\Models\Traits;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Arr;

trait HasTreeStructure
{
    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class);
    }

    /**
     * The model's recursive parents
     */
    public function ancestors(): BelongsTo
    {
        return $this->parent()
            ->with('parent');
    }

    #[Scope]
    public function parentless(Builder $builder): Builder
    {
        // more efficient than $builder->doesntHave('parent')
        return $builder->whereNull('parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id');
    }

    /**
     * The model's recursive children
     */
    public function descendants(): HasMany
    {
        return $this->children()
            ->with('children');
    }

    #[Scope]
    public function childless(Builder $builder): Builder
    {
        return $builder->doesntHave('children');
    }

    // public function siblings()
    // {
    //     return $this->parent()
    //         ->with('children')
    //         ->children;
    // }

    /**
     * The model's recursive parents and children
     */
    // public function bloodline(): Builder
    // {
    //     return $this
    //         ->with('ancestors')
    //         ->with('descendants');
    // }

    /**
     * The complete tree structure
     */
    #[Scope]
    public function tree(Builder $builder): Builder
    {
        return $builder->parentless()
            ->with('descendants');
    }

    /**
     * The model's recursive parents
     */
    public function getAncestorsIds(): array
    {
        $this->loadMissing('ancestors');

        if (!$this->ancestors) {
            return [];
        }

        $ancestorsIds = [];
        $hasParent = true;
        $currentParent = $this->ancestors;

        while ($hasParent) {
            if (!$currentParent->parent) {
                $hasParent = false;
            }

            $ancestorsIds[] = $currentParent->getKey();
            $currentParent = $currentParent->parent;
        }

        return $ancestorsIds;
    }

    /**
     * The model's recursive parents and itself
     */
    public function getAncestorsIdsAndSelf(): array
    {
        return Arr::prepend(
            $this->getAncestorsIds(),
            $this->getKey(),
        );
    }

    /**
     * The model's recursive children
     */
    public function getDescendantsIds(): array
    {
        $this->loadMissing('descendants');

        if ($this->descendants->isEmpty()) {
            return [];
        }

        $childrenIds = [];
        foreach ($this->descendants as $child) {
            $childrenIds[] = $this->extractChildrenIds($child);
        }

        return Arr::flatten($childrenIds);
    }

    /**
     * The model's recursive children and itself
     */
    public function getDescendantsIdsAndSelf(): array
    {
        return Arr::prepend(
            $this->getDescendantsIds(),
            $this->getKey(),
        );
    }

    private function extractChildrenIds(self $model): array
    {
        $ids = [
            $model->getKey(),
        ];

        if (!$model->has('children')) {
            return $ids;
        }

        foreach ($model->children as $key => $child) {
            if (!$child->has('children')) {
                $ids[] = $child->getKey();

                continue;
            }

            $ids[] = $this->extractChildrenIds($child);
        }

        return $ids;
    }
}
