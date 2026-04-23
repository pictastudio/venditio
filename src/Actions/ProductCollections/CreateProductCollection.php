<?php

namespace PictaStudio\Venditio\Actions\ProductCollections;

use Illuminate\Support\Arr;
use PictaStudio\Venditio\Models\ProductCollection;
use PictaStudio\Venditio\Support\CatalogImage;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class CreateProductCollection
{
    public function handle(array $payload): ProductCollection
    {
        $imagesProvided = array_key_exists('images', $payload);
        $images = Arr::pull($payload, 'images');

        if ($imagesProvided) {
            CatalogImage::validatePayload($images, []);
        }

        /** @var ProductCollection $collection */
        $collection = resolve_model('product_collection')::create($payload);

        if ($imagesProvided) {
            $collection->images = CatalogImage::mergeCollection($collection, [], $images, 'product_collections');
            $collection->save();
        }

        return $collection->refresh();
    }
}
