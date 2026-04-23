<?php

namespace PictaStudio\Venditio\Actions\Brands;

use Illuminate\Support\Arr;
use PictaStudio\Venditio\Models\Brand;
use PictaStudio\Venditio\Support\CatalogImage;

class UpdateBrand
{
    public function handle(Brand $brand, array $payload): Brand
    {
        $imagesProvided = array_key_exists('images', $payload);
        $tagIdsProvided = array_key_exists('tag_ids', $payload);
        $images = Arr::pull($payload, 'images');
        $tagIds = Arr::pull($payload, 'tag_ids', []);

        if ($imagesProvided) {
            $currentImages = CatalogImage::normalizeCollection($brand->getAttribute('images'));
            CatalogImage::validatePayload($images, CatalogImage::collectUsedIds($currentImages), 'images', $currentImages);

            $payload['images'] = CatalogImage::mergeCollection($brand, $currentImages, $images, 'brands');
        }

        $brand->fill($payload);
        $brand->save();

        if ($tagIdsProvided) {
            $brand->tags()->sync($tagIds ?? []);
        }

        return $brand->refresh()->load('tags');
    }
}
