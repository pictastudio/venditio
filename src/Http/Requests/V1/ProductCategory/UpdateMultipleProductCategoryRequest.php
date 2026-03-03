<?php

namespace PictaStudio\Venditio\Http\Requests\V1\ProductCategory;

use Illuminate\Foundation\Http\FormRequest;
use PictaStudio\Venditio\Validations\Contracts\ProductCategoryValidationRules;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class UpdateMultipleProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(ProductCategoryValidationRules $productCategoryValidationRules): array
    {
        return $productCategoryValidationRules->getBulkUpdateValidationRules();
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $categories = $this->input('categories', []);
            $incomingParents = [];
            $indexById = [];

            foreach ($categories as $index => $category) {
                if (
                    !array_key_exists('parent_id', $category)
                    || !array_key_exists('id', $category)
                ) {
                    continue;
                }

                $id = (int) $category['id'];
                $parentId = $category['parent_id'] === null
                    ? null
                    : (int) $category['parent_id'];

                $incomingParents[$id] = $parentId;
                $indexById[$id] = $index;

                if ($parentId === null) {
                    continue;
                }

                if ((int) $category['parent_id'] === (int) $category['id']) {
                    $validator->errors()->add(
                        "categories.{$index}.parent_id",
                        'The parent_id field must be different from id.'
                    );
                }
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $currentParents = resolve_model('product_category')::query()
                ->get()
                ->mapWithKeys(
                    fn ($category): array => [
                        (int) $category->getKey() => $category->parent_id === null
                            ? null
                            : (int) $category->parent_id,
                    ]
                )
                ->all();

            foreach (array_keys($incomingParents) as $categoryId) {
                if (!$this->createsCircularReference($categoryId, $incomingParents, $currentParents)) {
                    continue;
                }

                $validator->errors()->add(
                    'categories.' . $indexById[$categoryId] . '.parent_id',
                    'The parent_id field creates a circular reference.'
                );
            }
        });
    }

    private function createsCircularReference(int $startId, array $incomingParents, array $currentParents): bool
    {
        $visited = [];
        $cursor = $startId;

        while ($cursor !== null) {
            if (isset($visited[$cursor])) {
                return true;
            }

            $visited[$cursor] = true;
            $cursor = array_key_exists($cursor, $incomingParents)
                ? $incomingParents[$cursor]
                : ($currentParents[$cursor] ?? null);
        }

        return false;
    }
}
