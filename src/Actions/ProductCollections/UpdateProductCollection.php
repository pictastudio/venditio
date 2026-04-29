<?php

namespace PictaStudio\Venditio\Actions\ProductCollections;

use Illuminate\Support\Arr;
use PictaStudio\Venditio\Models\ProductCollection;
use PictaStudio\Venditio\Support\CatalogImage;

class UpdateProductCollection
{
    public function handle(ProductCollection $collection, array $payload): ProductCollection
    {
        $imagesProvided = array_key_exists('images', $payload);
        $tagIdsProvided = array_key_exists('tag_ids', $payload);
        $images = Arr::pull($payload, 'images');
        $tagIds = Arr::pull($payload, 'tag_ids', []);

        if ($imagesProvided) {
            $currentImages = CatalogImage::normalizeCollection($collection->getAttribute('images'));
            CatalogImage::validatePayload($images, CatalogImage::collectUsedIds($currentImages), 'images', $currentImages);

            $payload['images'] = CatalogImage::mergeCollection($collection, $currentImages, $images, 'product_collections');
        }

        $collection->fill($payload);
        $collection->save();

        if ($tagIdsProvided) {
            $collection->tags()->sync($tagIds ?? []);
        }

        return $collection->refresh()->load('tags');
    }
}
