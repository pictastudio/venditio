<?php

namespace PictaStudio\Venditio\Actions\Tags;

use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use PictaStudio\Venditio\Models\Tag;
use PictaStudio\Venditio\Support\CatalogImage;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class CreateTag
{
    public function handle(array $payload): Tag
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

        $parentId = Arr::get($payload, 'parent_id');
        $parent = $this->resolveParent($parentId);
        $payload['product_type_id'] = $this->resolveProductTypeId(
            $parent,
            Arr::get($payload, 'product_type_id')
        );

        if ($imagesProvided) {
            CatalogImage::validatePayload($images, []);
        }

        if ($productIdsProvided) {
            $this->validateProductTypeCompatibility($productIds ?? [], $payload['product_type_id'] ?? null);
        }

        /** @var Tag $tag */
        $tag = resolve_model('tag')::create($payload);

        if ($imagesProvided) {
            $tag->images = CatalogImage::mergeCollection($tag, [], $images, 'tags');
            $tag->save();
        }

        if ($tagIdsProvided) {
            $tag->tags()->sync($tagIds ?? []);
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
