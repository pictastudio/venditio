<?php

namespace PictaStudio\Venditio\Actions\Tags;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use PictaStudio\Venditio\Actions\Tree\RebuildTreePaths;
use PictaStudio\Venditio\Models\Tag;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class UpdateMultipleTags
{
    public function __construct(
        private readonly RebuildTreePaths $treePaths,
    ) {}

    public function handle(array $tags): Collection
    {
        return DB::transaction(function () use ($tags): Collection {
            $tagIds = collect($tags)
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();

            /** @var Collection<int, Tag> $models */
            $models = resolve_model('tag')::query()
                ->whereKey($tagIds)
                ->get()
                ->keyBy(fn (Tag $tag): int => (int) $tag->getKey());

            $updatedTags = new Collection;
            $tagsToRebuild = [];

            foreach ($tags as $tagPayload) {
                /** @var Tag $tag */
                $tag = $models->get((int) $tagPayload['id']);
                $isParentChanging = (int) $tag->parent_id !== (int) ($tagPayload['parent_id'] ?? 0)
                    || (
                        $tag->parent_id === null
                        && $tagPayload['parent_id'] !== null
                    )
                    || (
                        $tag->parent_id !== null
                        && $tagPayload['parent_id'] === null
                    );

                $parent = $this->resolveParent($tagPayload['parent_id']);

                $tag->fill([
                    'parent_id' => $tagPayload['parent_id'],
                    'product_type_id' => $parent?->product_type_id ?? $tag->product_type_id,
                    'sort_order' => (int) $tagPayload['sort_order'],
                ]);
                $tag->saveQuietly();

                $this->propagateProductTypeToChildren($tag->refresh());

                $updatedTags->push($tag->refresh()->load(['productType', 'tags']));

                if ($isParentChanging) {
                    $tagsToRebuild[] = (int) $tag->getKey();
                }
            }

            foreach (array_unique($tagsToRebuild) as $tagId) {
                /** @var Tag|null $tag */
                $tag = resolve_model('tag')::query()->find($tagId);

                if ($tag === null) {
                    continue;
                }

                $this->treePaths->rebuild($tag);
            }

            return $updatedTags;
        });
    }

    private function resolveParent(mixed $parentId): ?Tag
    {
        if (!is_numeric($parentId)) {
            return null;
        }

        /** @var Tag|null $parent */
        return resolve_model('tag')::withoutGlobalScopes()->find((int) $parentId);
    }

    private function propagateProductTypeToChildren(Tag $tag): void
    {
        $children = resolve_model('tag')::withoutGlobalScopes()
            ->where('parent_id', $tag->getKey())
            ->get();

        foreach ($children as $child) {
            /** @var Tag $child */
            $child->product_type_id = $tag->product_type_id;
            $child->saveQuietly();

            $this->propagateProductTypeToChildren($child->refresh());
        }
    }
}
