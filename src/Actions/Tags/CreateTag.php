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

        /** @var Tag $tag */
        $tag = resolve_model('tag')::create($payload);

        if ($imagesProvided) {
            $tag->images = CatalogImage::mergeCollection($tag, [], $images, 'tags');
            $tag->save();
        }

        if ($tagIdsProvided) {
            $tag->tags()->sync($tagIds ?? []);
        }

        return $tag->refresh()->load(['productType', 'tags']);
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
}
