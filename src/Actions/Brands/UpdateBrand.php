<?php

namespace PictaStudio\Venditio\Actions\Brands;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use PictaStudio\Venditio\Models\Brand;

class UpdateBrand
{
    public function handle(Brand $brand, array $payload): Brand
    {
        $thumbProvided = array_key_exists('img_thumb', $payload);
        $coverProvided = array_key_exists('img_cover', $payload);
        $tagIdsProvided = array_key_exists('tag_ids', $payload);
        $thumb = Arr::pull($payload, 'img_thumb');
        $cover = Arr::pull($payload, 'img_cover');
        $tagIds = Arr::pull($payload, 'tag_ids', []);

        $brand->fill($payload);

        if ($thumbProvided) {
            $brand->img_thumb = $this->storeImage($brand, $thumb, 'img_thumb');
        }

        if ($coverProvided) {
            $brand->img_cover = $this->storeImage($brand, $cover, 'img_cover');
        }

        $brand->save();

        if ($tagIdsProvided) {
            $brand->tags()->sync($tagIds ?? []);
        }

        return $brand->refresh()->load('tags');
    }

    private function storeImage(Brand $brand, mixed $payload, string $folder): ?array
    {
        if ($payload === null) {
            return null;
        }

        if (!is_array($payload) || !isset($payload['file']) || !$payload['file'] instanceof UploadedFile) {
            return null;
        }

        return [
            'src' => $payload['file']->store("brands/{$brand->getKey()}/{$folder}", 'public'),
            'alt' => Arr::get($payload, 'alt'),
            'name' => Arr::get($payload, 'name'),
        ];
    }
}
