<?php

namespace PictaStudio\Venditio\Actions\ProductCategories;

use Illuminate\Support\Arr;
use PictaStudio\Venditio\Models\ProductCategory;
use PictaStudio\Venditio\Support\CatalogImage;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class CreateProductCategory
{
    public function handle(array $payload): ProductCategory
    {
        $imagesProvided = array_key_exists('images', $payload);
        $tagIdsProvided = array_key_exists('tag_ids', $payload);
        $images = Arr::pull($payload, 'images');
        $tagIds = Arr::pull($payload, 'tag_ids', []);

        if ($imagesProvided) {
            CatalogImage::validatePayload($images, []);
        }

        /** @var ProductCategory $category */
        $category = resolve_model('product_category')::create($payload);

        if ($imagesProvided) {
            $category->images = CatalogImage::mergeCollection($category, [], $images, 'product_categories');
            $category->save();
        }

        if ($tagIdsProvided) {
            $category->tags()->sync($tagIds ?? []);
        }

        return $category->refresh()->load('tags');
    }
}
