<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PictaStudio\Venditio\Models\{Brand, ProductCategory, ProductCollection, Tag};

use function Pest\Laravel\deleteJson;

uses(RefreshDatabase::class);

it('deletes catalog images by unique id for every catalog image owner', function (string $modelClass, string $uri): void {
    Storage::fake('public');

    config()->set('venditio.catalog.images.delete_files_from_filesystem', true);

    $path = "{$uri}/1/images/delete-me.jpg";
    Storage::disk('public')->put($path, 'content');

    /** @var Model $model */
    $model = $modelClass::factory()->create([
        'images' => [
            [
                'id' => 'delete-image',
                'type' => 'thumb',
                'alt' => 'Delete image',
                'mimetype' => 'image/jpeg',
                'sort_order' => 0,
                'src' => $path,
            ],
            [
                'id' => 'keep-image',
                'type' => 'cover',
                'alt' => 'Keep image',
                'mimetype' => 'image/jpeg',
                'sort_order' => 1,
                'src' => "{$uri}/1/images/keep-me.jpg",
            ],
        ],
    ]);

    deleteJson(config('venditio.routes.api.v1.prefix') . "/{$uri}/{$model->getKey()}/images/delete-image")
        ->assertNoContent();

    $model->refresh();

    expect($model->getAttribute('images'))->toHaveCount(1)
        ->and(data_get($model->getAttribute('images'), '0.id'))->toBe('keep-image');

    Storage::disk('public')->assertMissing($path);
})->with([
    'brand' => [Brand::class, 'brands'],
    'product category' => [ProductCategory::class, 'product_categories'],
    'product collection' => [ProductCollection::class, 'product_collections'],
    'tag' => [Tag::class, 'tags'],
]);

it('returns not found when the catalog image id does not exist', function (): void {
    $brand = Brand::factory()->create([
        'images' => [],
    ]);

    deleteJson(config('venditio.routes.api.v1.prefix') . "/brands/{$brand->getKey()}/images/missing-image")
        ->assertNotFound();
});

it('keeps catalog image files when filesystem deletion is disabled', function (): void {
    Storage::fake('public');

    config()->set('venditio.catalog.images.delete_files_from_filesystem', false);

    $path = 'brands/1/images/delete-me.jpg';
    Storage::disk('public')->put($path, 'content');

    $brand = Brand::factory()->create([
        'images' => [
            [
                'id' => 'delete-image',
                'type' => 'thumb',
                'mimetype' => 'image/jpeg',
                'sort_order' => 0,
                'src' => $path,
            ],
        ],
    ]);

    deleteJson(config('venditio.routes.api.v1.prefix') . "/brands/{$brand->getKey()}/images/delete-image")
        ->assertNoContent();

    $brand->refresh();

    expect($brand->getAttribute('images'))->toHaveCount(0);
    Storage::disk('public')->assertExists($path);
});

it('keeps catalog image files that are referenced by another catalog resource', function (): void {
    Storage::fake('public');

    config()->set('venditio.catalog.images.delete_files_from_filesystem', true);

    $path = 'shared/catalog-image.jpg';
    Storage::disk('public')->put($path, 'content');

    $brand = Brand::factory()->create([
        'images' => [
            [
                'id' => 'delete-image',
                'type' => 'thumb',
                'mimetype' => 'image/jpeg',
                'sort_order' => 0,
                'src' => $path,
            ],
        ],
    ]);

    ProductCategory::factory()->create([
        'images' => [
            [
                'id' => 'shared-image',
                'type' => 'thumb',
                'mimetype' => 'image/jpeg',
                'sort_order' => 0,
                'src' => $path,
            ],
        ],
    ]);

    deleteJson(config('venditio.routes.api.v1.prefix') . "/brands/{$brand->getKey()}/images/delete-image")
        ->assertNoContent();

    Storage::disk('public')->assertExists($path);
});
