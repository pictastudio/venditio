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
        $images = Arr::pull($payload, 'images');

        if ($imagesProvided) {
            $currentImages = CatalogImage::normalizeCollection($collection->getAttribute('images'));
            CatalogImage::validatePayload($images, CatalogImage::collectUsedIds($currentImages), 'images', $currentImages);

            $payload['images'] = CatalogImage::mergeCollection($collection, $currentImages, $images, 'product_collections');
        }

        $collection->fill($payload);
        $collection->save();

        return $collection->refresh();
    }
}
