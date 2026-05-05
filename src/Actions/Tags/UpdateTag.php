<?php

namespace PictaStudio\Venditio\Actions\Tags;

use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use PictaStudio\Venditio\Models\Tag;
use PictaStudio\Venditio\Support\CatalogImage;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class UpdateTag
{
    public function handle(Tag $tag, array $payload): Tag
    {
        $tagIdsProvided = array_key_exists('tag_ids', $payload);
        $tagIds = Arr::pull($payload, 'tag_ids', []);
        $productIdsProvided = array_key_exists('product_ids', $payload);
        $productIds = Arr::pull($payload, 'product_ids', []);
        $brandIdsProvided = array_key_exists('brand_ids', $payload);
        $brandIds = Arr::pull($payload, 'brand_ids', []);
        $productCategoryIdsProvided = array_key_exists('product_category_ids', $payload);
        $productCategoryIds = Arr::pull($payload, 'product_category_ids', []);
        $productCollectionIdsProvided = array_key_exists('product_collection_ids', $payload);
        $productCollectionIds = Arr::pull($payload, 'product_collection_ids', []);
        $imagesProvided = array_key_exists('images', $payload);
        $images = Arr::pull($payload, 'images');
        $tagIdsCollection = collect($tagIds ?? [])
            ->map(fn (mixed $id): int => (int) $id);

        if ($tagIdsProvided && $tagIdsCollection->contains((int) $tag->getKey())) {
            throw ValidationException::withMessages([
                'tag_ids' => ['A tag cannot be attached to itself.'],
            ]);
        }

        $parentId = array_key_exists('parent_id', $payload)
            ? $payload['parent_id']
            : $tag->parent_id;

        $this->guardAgainstInvalidParent($tag, $parentId);

        $candidateProductTypeId = array_key_exists('product_type_id', $payload)
            ? $payload['product_type_id']
            : $tag->product_type_id;

        $parent = $this->resolveParent($parentId);
        $payload['product_type_id'] = $this->resolveProductTypeId($parent, $candidateProductTypeId);

        if ($productIdsProvided) {
            $this->validateProductTypeCompatibility($productIds ?? [], $payload['product_type_id'] ?? null);
        }

        if ($imagesProvided) {
            $currentImages = CatalogImage::normalizeCollection($tag->getAttribute('images'));
            CatalogImage::validatePayload($images, CatalogImage::collectUsedIds($currentImages), 'images', $currentImages);
            $payload['images'] = CatalogImage::mergeCollection($tag, $currentImages, $images, 'tags');
        }

        $tag->fill($payload);
        $tag->save();

        $this->propagateProductTypeToChildren($tag->refresh());

        if ($tagIdsProvided) {
            $tag->tags()->sync($tagIdsCollection->all());
        }

        if ($productIdsProvided) {
            $tag->products()->sync($productIds ?? []);
        }

        if ($brandIdsProvided) {
            $tag->brands()->sync($brandIds ?? []);
        }

        if ($productCategoryIdsProvided) {
            $tag->productCategories()->sync($productCategoryIds ?? []);
        }

        if ($productCollectionIdsProvided) {
            $tag->productCollections()->sync($productCollectionIds ?? []);
        }

        return $tag->refresh()->load(['productType', 'tags', 'products', 'brands', 'productCategories', 'productCollections']);
    }

    private function guardAgainstInvalidParent(Tag $tag, mixed $parentId): void
    {
        if (!is_numeric($parentId)) {
            return;
        }

        $parentId = (int) $parentId;

        if ($parentId === (int) $tag->getKey()) {
            throw ValidationException::withMessages([
                'parent_id' => ['A tag cannot be its own parent.'],
            ]);
        }

        if ($this->isDescendantOf($tag, $parentId)) {
            throw ValidationException::withMessages([
                'parent_id' => ['A tag cannot be moved under one of its descendants.'],
            ]);
        }
    }

    private function isDescendantOf(Tag $tag, int $candidateParentId): bool
    {
        $children = resolve_model('tag')::withoutGlobalScopes()
            ->where('parent_id', $tag->getKey())
            ->get();

        foreach ($children as $child) {
            if ((int) $child->getKey() === $candidateParentId) {
                return true;
            }

            if ($this->isDescendantOf($child, $candidateParentId)) {
                return true;
            }
        }

        return false;
    }

    private function resolveParent(mixed $parentId): ?Tag
    {
        if (!is_numeric($parentId)) {
            return null;
        }

        /** @var Tag|null $parent */
        return resolve_model('tag')::withoutGlobalScopes()->find((int) $parentId);
    }

    private function resolveProductTypeId(?Tag $parent, mixed $payloadProductTypeId): ?int
    {
        $resolvedProductTypeId = is_numeric($payloadProductTypeId)
            ? (int) $payloadProductTypeId
            : null;

        if ($parent === null || !is_numeric($parent->product_type_id)) {
            return $resolvedProductTypeId;
        }

        if ($resolvedProductTypeId !== null && $resolvedProductTypeId !== (int) $parent->product_type_id) {
            throw ValidationException::withMessages([
                'product_type_id' => [
                    'The selected product_type_id must match the parent tag product_type_id.',
                ],
            ]);
        }

        return (int) $parent->product_type_id;
    }

    private function propagateProductTypeToChildren(Tag $tag): void
    {
        $children = resolve_model('tag')::withoutGlobalScopes()
            ->where('parent_id', $tag->getKey())
            ->get();

        foreach ($children as $child) {
            $child->product_type_id = $tag->product_type_id;
            $child->saveQuietly();

            $this->propagateProductTypeToChildren($child->refresh());
        }
    }

    private function validateProductTypeCompatibility(array $productIds, mixed $productTypeId): void
    {
        if ($productIds === [] || !is_numeric($productTypeId)) {
            return;
        }

        $invalidProducts = resolve_model('product')::withoutGlobalScopes()
            ->whereKey($productIds)
            ->where('product_type_id', '!=', (int) $productTypeId)
            ->pluck('id')
            ->values()
            ->all();

        if ($invalidProducts === []) {
            return;
        }

        throw ValidationException::withMessages([
            'product_ids' => [
                'The selected products are not compatible with the tag product type: ' . implode(', ', $invalidProducts),
            ],
        ]);
    }
}
