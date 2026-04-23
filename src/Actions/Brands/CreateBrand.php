<?php

namespace PictaStudio\Venditio\Actions\Brands;

use Illuminate\Support\Arr;
use PictaStudio\Venditio\Models\Brand;
use PictaStudio\Venditio\Support\CatalogImage;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class CreateBrand
{
    public function handle(array $payload): Brand
    {
        $imagesProvided = array_key_exists('images', $payload);
        $tagIdsProvided = array_key_exists('tag_ids', $payload);
        $images = Arr::pull($payload, 'images');
        $tagIds = Arr::pull($payload, 'tag_ids', []);

        if ($imagesProvided) {
            CatalogImage::validatePayload($images, []);
        }

        /** @var Brand $brand */
        $brand = resolve_model('brand')::create($payload);

        if ($imagesProvided) {
            $brand->images = CatalogImage::mergeCollection($brand, [], $images, 'brands');
            $brand->save();
        }

        if ($tagIdsProvided) {
            $brand->tags()->sync($tagIds ?? []);
        }

        return $brand->refresh()->load('tags');
    }
}
