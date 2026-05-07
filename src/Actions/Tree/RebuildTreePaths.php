<?php

namespace PictaStudio\Venditio\Actions\Tree;

use Illuminate\Database\Eloquent\Model;

class RebuildTreePaths
{
    public function rebuild(Model $node, ?Model $parent = null): void
    {
        $node->refresh();

        if ($parent !== null) {
            $node->setRelation('parent', $parent);
        }

        $node->assignPath();
        $node->saveQuietly();

        $children = $node->newQueryWithoutScopes()
            ->where('parent_id', $node->getKey())
            ->get();

        foreach ($children as $child) {
            $this->rebuild($child, $node);
        }
    }

    public function releaseChildrenToRoot(Model $parent): void
    {
        $children = $parent->newQueryWithoutScopes()
            ->where('parent_id', $parent->getKey())
            ->get();

        foreach ($children as $child) {
            $child->setAttribute('parent_id', null);
            $child->saveQuietly();

            $this->rebuild($child);
        }
    }
}
