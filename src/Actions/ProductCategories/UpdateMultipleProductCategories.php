<?php

namespace PictaStudio\Venditio\Actions\ProductCategories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use PictaStudio\Venditio\Actions\Tree\RebuildTreePaths;
use PictaStudio\Venditio\Models\ProductCategory;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class UpdateMultipleProductCategories
{
    public function __construct(
        private readonly RebuildTreePaths $treePaths,
    ) {}

    public function handle(array $categories): Collection
    {
        return DB::transaction(function () use ($categories): Collection {
            $categoryIds = collect($categories)
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();

            /** @var Collection<int, ProductCategory> $models */
            $models = resolve_model('product_category')::query()
                ->whereKey($categoryIds)
                ->get()
                ->keyBy(fn (ProductCategory $category): int => (int) $category->getKey());

            $updatedCategories = new Collection;
            $categoriesToRebuild = [];

            foreach ($categories as $categoryPayload) {
                /** @var ProductCategory $category */
                $category = $models->get((int) $categoryPayload['id']);
                $isParentChanging = (int) $category->parent_id !== (int) ($categoryPayload['parent_id'] ?? 0)
                    || (
                        $category->parent_id === null
                        && $categoryPayload['parent_id'] !== null
                    )
                    || (
                        $category->parent_id !== null
                        && $categoryPayload['parent_id'] === null
                    );

                $category->fill([
                    'parent_id' => $categoryPayload['parent_id'],
                    'sort_order' => (int) $categoryPayload['sort_order'],
                ]);
                $category->saveQuietly();

                $updatedCategories->push($category->refresh());

                if ($isParentChanging) {
                    $categoriesToRebuild[] = (int) $category->getKey();
                }
            }

            foreach (array_unique($categoriesToRebuild) as $categoryId) {
                /** @var ProductCategory|null $category */
                $category = resolve_model('product_category')::query()->find($categoryId);

                if ($category === null) {
                    continue;
                }

                $this->treePaths->rebuild($category);
            }

            return $updatedCategories;
        });
    }
}
