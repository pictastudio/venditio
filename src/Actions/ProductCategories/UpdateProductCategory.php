<?php

namespace PictaStudio\Venditio\Actions\ProductCategories;

use Illuminate\Support\Arr;
use PictaStudio\Venditio\Models\ProductCategory;
use PictaStudio\Venditio\Support\CatalogImage;

class UpdateProductCategory
{
    public function handle(ProductCategory $category, array $payload): ProductCategory
    {
        $imagesProvided = array_key_exists('images', $payload);
        $tagIdsProvided = array_key_exists('tag_ids', $payload);
        $images = Arr::pull($payload, 'images');
        $tagIds = Arr::pull($payload, 'tag_ids', []);

        if ($imagesProvided) {
            $currentImages = CatalogImage::normalizeCollection($category->getAttribute('images'));
            CatalogImage::validatePayload($images, CatalogImage::collectUsedIds($currentImages), 'images', $currentImages);

            $payload['images'] = CatalogImage::mergeCollection($category, $currentImages, $images, 'product_categories');
        }

        $category->fill($payload);
        $category->save();

        if ($tagIdsProvided) {
            $category->tags()->sync($tagIds ?? []);
        }

        return $category->refresh()->load('tags');
    }
}
