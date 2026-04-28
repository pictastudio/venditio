<?php

namespace PictaStudio\Venditio\Actions\CatalogImages;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\{Storage, URL};
use PictaStudio\Venditio\Support\CatalogImage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class DeleteCatalogImage
{
    public function handle(Model $model, string $imageId): Model
    {
        $images = CatalogImage::normalizeCollection($model->getAttribute('images'));
        $deletedImage = null;

        $images = collect($images)
            ->reject(function (array $image) use ($imageId, &$deletedImage): bool {
                $matches = Arr::get($image, 'id') === $imageId;

                if ($matches) {
                    $deletedImage = $image;
                }

                return $matches;
            })
            ->values()
            ->all();

        if ($deletedImage === null) {
            throw new NotFoundHttpException('Catalog image not found.');
        }

        $model->forceFill([
            'images' => $images,
        ]);
        $model->save();

        if ($this->shouldDeleteFromFilesystem()) {
            $this->deleteFromFilesystem(Arr::get($deletedImage, 'src'), $model);
        }

        return $model->refresh();
    }

    private function shouldDeleteFromFilesystem(): bool
    {
        return (bool) config('venditio.catalog.images.delete_files_from_filesystem', true);
    }

    private function deleteFromFilesystem(mixed $path, Model $deletedFromModel): void
    {
        if (!is_string($path) || blank($path) || URL::isValidUrl($path)) {
            return;
        }

        if ($this->isReferencedByAnotherCatalogResource($path, $deletedFromModel)) {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    private function isReferencedByAnotherCatalogResource(string $path, Model $deletedFromModel): bool
    {
        foreach (['brand', 'product_category', 'product_collection', 'tag'] as $modelAlias) {
            $modelClass = resolve_model($modelAlias);
            $query = $modelClass::withoutGlobalScopes();

            if ($deletedFromModel instanceof $modelClass) {
                $query->whereKeyNot($deletedFromModel->getKey());
            }

            $isReferenced = $query
                ->get(['images'])
                ->contains(fn (Model $model): bool => collect(CatalogImage::normalizeCollection($model->getAttribute('images')))
                    ->contains(fn (array $image): bool => Arr::get($image, 'src') === $path));

            if ($isReferenced) {
                return true;
            }
        }

        return false;
    }
}
