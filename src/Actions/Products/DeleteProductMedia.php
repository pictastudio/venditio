<?php

namespace PictaStudio\Venditio\Actions\Products;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\{Storage, URL};
use PictaStudio\Venditio\Models\Product;
use PictaStudio\Venditio\Support\ProductMedia;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DeleteProductMedia
{
    public function handle(Product $product, string $mediaId): Product
    {
        $media = ProductMedia::normalizeProductMedia(
            $product->getAttribute('images'),
            $product->getAttribute('files')
        );

        $deletedMedia = null;

        [$media['images'], $deletedMedia] = $this->removeFromCollection($media['images'], $mediaId);

        if ($deletedMedia === null) {
            [$media['files'], $deletedMedia] = $this->removeFromCollection($media['files'], $mediaId);
        }

        if ($deletedMedia === null) {
            throw new NotFoundHttpException('Product media not found.');
        }

        if ($this->shouldDeleteFromFilesystem()) {
            $this->deleteFromFilesystem(Arr::get($deletedMedia, 'src'));
        }

        $product->forceFill([
            'images' => $media['images'],
            'files' => $media['files'],
        ]);
        $product->save();

        return $product->refresh();
    }

    /**
     * @param  array<int, array{id: string}>  $items
     * @return array{0: array<int, array{id: string}>, 1: array<string, mixed>|null}
     */
    private function removeFromCollection(array $items, string $mediaId): array
    {
        $deletedMedia = null;

        $items = collect($items)
            ->reject(function (array $item) use ($mediaId, &$deletedMedia): bool {
                $matches = Arr::get($item, 'id') === $mediaId;

                if ($matches) {
                    $deletedMedia = $item;
                }

                return $matches;
            })
            ->values()
            ->all();

        return [$items, $deletedMedia];
    }

    private function shouldDeleteFromFilesystem(): bool
    {
        return (bool) config('venditio.product.media.delete_files_from_filesystem', true);
    }

    private function deleteFromFilesystem(mixed $path): void
    {
        if (!is_string($path) || blank($path) || URL::isValidUrl($path)) {
            return;
        }

        Storage::disk('public')->delete($path);
    }
}
