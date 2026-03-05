<?php

namespace PictaStudio\Venditio\Actions\ProductTags;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use PictaStudio\Venditio\Models\ProductTag;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class CreateProductTag
{
    public function handle(array $payload): ProductTag
    {
        $tagIdsProvided = array_key_exists('tag_ids', $payload);
        $tagIds = Arr::pull($payload, 'tag_ids', []);
        $thumbProvided = array_key_exists('img_thumb', $payload);
        $coverProvided = array_key_exists('img_cover', $payload);
        $thumb = Arr::pull($payload, 'img_thumb');
        $cover = Arr::pull($payload, 'img_cover');

        $parentId = Arr::get($payload, 'parent_id');
        $parent = $this->resolveParent($parentId);
        $payload['product_type_id'] = $this->resolveProductTypeId(
            $parent,
            Arr::get($payload, 'product_type_id')
        );

        /** @var ProductTag $tag */
        $tag = resolve_model('product_tag')::create($payload);

        if ($thumbProvided) {
            $tag->img_thumb = $this->storeImage($tag, $thumb, 'img_thumb');
        }

        if ($coverProvided) {
            $tag->img_cover = $this->storeImage($tag, $cover, 'img_cover');
        }

        if ($thumbProvided || $coverProvided) {
            $tag->save();
        }

        if ($tagIdsProvided) {
            $tag->tags()->sync($tagIds ?? []);
        }

        return $tag->refresh()->load(['productType', 'tags']);
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
