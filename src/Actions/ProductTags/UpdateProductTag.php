<?php

namespace PictaStudio\Venditio\Actions\ProductTags;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use PictaStudio\Venditio\Models\ProductTag;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class UpdateProductTag
{
    public function handle(ProductTag $tag, array $payload): ProductTag
    {
        $tagIdsProvided = array_key_exists('tag_ids', $payload);
        $tagIds = Arr::pull($payload, 'tag_ids', []);
        $thumbProvided = array_key_exists('img_thumb', $payload);
        $coverProvided = array_key_exists('img_cover', $payload);
        $thumb = Arr::pull($payload, 'img_thumb');
        $cover = Arr::pull($payload, 'img_cover');
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

        $tag->fill($payload);

        if ($thumbProvided) {
            $tag->img_thumb = $this->storeImage($tag, $thumb, 'img_thumb');
        }

        if ($coverProvided) {
            $tag->img_cover = $this->storeImage($tag, $cover, 'img_cover');
        }

        $tag->save();

        $this->propagateProductTypeToChildren($tag->refresh());

        if ($tagIdsProvided) {
            $tag->tags()->sync($tagIdsCollection->all());
        }

        return $tag->refresh()->load(['productType', 'tags']);
    }

    private function guardAgainstInvalidParent(ProductTag $tag, mixed $parentId): void
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

    private function isDescendantOf(ProductTag $tag, int $candidateParentId): bool
    {
        $children = resolve_model('product_tag')::withoutGlobalScopes()
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

    private function resolveParent(mixed $parentId): ?ProductTag
    {
        if (!is_numeric($parentId)) {
            return null;
        }

        /** @var ProductTag|null $parent */
        return resolve_model('product_tag')::withoutGlobalScopes()->find((int) $parentId);
    }

    private function resolveProductTypeId(?ProductTag $parent, mixed $payloadProductTypeId): ?int
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

    private function propagateProductTypeToChildren(ProductTag $tag): void
    {
        $children = resolve_model('product_tag')::withoutGlobalScopes()
            ->where('parent_id', $tag->getKey())
            ->get();

        foreach ($children as $child) {
            $child->product_type_id = $tag->product_type_id;
            $child->saveQuietly();

            $this->propagateProductTypeToChildren($child->refresh());
        }
    }

    private function storeImage(ProductTag $tag, mixed $payload, string $folder): ?array
    {
        if ($payload === null) {
            return null;
        }

        if (!is_array($payload) || !isset($payload['file']) || !$payload['file'] instanceof UploadedFile) {
            return null;
        }

        return [
            'src' => $payload['file']->store("product_tags/{$tag->getKey()}/{$folder}", 'public'),
            'alt' => Arr::get($payload, 'alt'),
            'name' => Arr::get($payload, 'name'),
        ];
    }
}
