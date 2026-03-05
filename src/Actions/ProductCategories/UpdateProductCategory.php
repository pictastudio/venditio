<?php

namespace PictaStudio\Venditio\Actions\ProductCategories;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use PictaStudio\Venditio\Models\ProductCategory;

class UpdateProductCategory
{
    public function handle(ProductCategory $category, array $payload): ProductCategory
    {
        $thumbProvided = array_key_exists('img_thumb', $payload);
        $coverProvided = array_key_exists('img_cover', $payload);
        $tagIdsProvided = array_key_exists('tag_ids', $payload);
        $thumb = Arr::pull($payload, 'img_thumb');
        $cover = Arr::pull($payload, 'img_cover');
        $tagIds = Arr::pull($payload, 'tag_ids', []);

        $category->fill($payload);

        if ($thumbProvided) {
            $category->img_thumb = $this->storeImage($category, $thumb, 'img_thumb');
        }

        if ($coverProvided) {
            $category->img_cover = $this->storeImage($category, $cover, 'img_cover');
        }

        $category->save();

        if ($tagIdsProvided) {
            $category->tags()->sync($tagIds ?? []);
        }

        return $category->refresh()->load('tags');
    }

    private function storeImage(ProductCategory $category, mixed $payload, string $folder): ?array
    {
        if ($payload === null) {
            return null;
        }

        if (!is_array($payload) || !isset($payload['file']) || !$payload['file'] instanceof UploadedFile) {
            return null;
        }

        return [
            'src' => $payload['file']->store("product_categories/{$category->getKey()}/{$folder}", 'public'),
            'alt' => Arr::get($payload, 'alt'),
            'name' => Arr::get($payload, 'name'),
        ];
    }
}
