<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PictaStudio\Venditio\Enums\ProductStatus;
use PictaStudio\Venditio\Models\{Product, TaxClass};

use function Pest\Laravel\{deleteJson, getJson, patch, patchJson, postJson};

uses(RefreshDatabase::class);

it('rejects images and files payload on product store request', function () {
    $taxClass = TaxClass::factory()->create();

    postJson(config('venditio.routes.api.v1.prefix') . '/products', [
        'tax_class_id' => $taxClass->getKey(),
        'name' => 'Media Product',
        'status' => ProductStatus::Published,
        'images' => [
            ['file' => 'not-a-file'],
        ],
        'files' => [
            ['file' => 'not-a-file'],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['images', 'files']);
});

it('requires an uploaded file for each images/files item on product update', function () {
    $product = Product::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    patchJson(config('venditio.routes.api.v1.prefix') . "/products/{$product->getKey()}", [
        'images' => [
            ['alt' => 'Hero'],
        ],
        'files' => [
            ['alt' => 'Manual'],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['images.0.file', 'files.0.file']);
});

it('uploads product images and files on update, appends them, and persists unique ids', function () {
    Storage::fake('public');

    $product = Product::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
        'images' => [
            [
                'id' => 'existing-image',
                'alt' => 'Existing image',
                'mimetype' => 'image/jpeg',
                'sort_order' => 10,
                'active' => true,
                'thumbnail' => false,
                'src' => 'products/existing-image.jpg',
            ],
        ],
        'files' => [
            [
                'id' => 'existing-file',
                'alt' => 'Existing file',
                'name' => 'existing.pdf',
                'mimetype' => 'application/pdf',
                'sort_order' => 10,
                'active' => true,
                'src' => 'products/existing-file.pdf',
            ],
        ],
    ]);

    patch(
        config('venditio.routes.api.v1.prefix') . "/products/{$product->getKey()}",
        [
            'images' => [
                [
                    'file' => UploadedFile::fake()->image('hero.jpg'),
                    'alt' => 'Hero image',
                    'mimetype' => 'image/custom-hero',
                    'sort_order' => 2,
                    'thumbnail' => true,
                ],
                [
                    'file' => UploadedFile::fake()->image('gallery.jpg'),
                    'sort_order' => 1,
                    'active' => true,
                ],
            ],
            'files' => [
                [
                    'file' => UploadedFile::fake()->create('manual.pdf', 200, 'application/pdf'),
                    'alt' => 'Manual PDF',
                    'mimetype' => 'application/x-custom-pdf',
                    'sort_order' => 2,
                ],
                [
                    'file' => UploadedFile::fake()->create('datasheet.txt', 10, 'text/plain'),
                    'sort_order' => 1,
                    'active' => true,
                ],
            ],
        ],
        ['Accept' => 'application/json']
    )->assertOk()
        ->assertJsonPath('images.0.sort_order', 1)
        ->assertJsonPath('images.0.mimetype', 'image/jpeg')
        ->assertJsonPath('images.0.thumbnail', false)
        ->assertJsonPath('images.1.mimetype', 'image/custom-hero')
        ->assertJsonPath('images.1.thumbnail', true)
        ->assertJsonPath('images.2.id', 'existing-image')
        ->assertJsonPath('files.0.sort_order', 1)
        ->assertJsonPath('files.0.mimetype', 'text/plain')
        ->assertJsonPath('files.1.mimetype', 'application/x-custom-pdf')
        ->assertJsonPath('files.2.id', 'existing-file');

    $product->refresh();

    expect($product->images)->toBeArray()
        ->and($product->files)->toBeArray()
        ->and(count($product->images))->toBe(3)
        ->and(count($product->files))->toBe(3)
        ->and(data_get($product->images, '0.alt'))->toBeNull()
        ->and(data_get($product->images, '0.mimetype'))->toBe('image/jpeg')
        ->and(data_get($product->images, '0.sort_order'))->toBe(1)
        ->and(data_get($product->images, '0.active'))->toBeTrue()
        ->and(data_get($product->images, '0.thumbnail'))->toBeFalse()
        ->and(data_get($product->images, '1.alt'))->toBe('Hero image')
        ->and(data_get($product->images, '1.mimetype'))->toBe('image/custom-hero')
        ->and(data_get($product->images, '1.sort_order'))->toBe(2)
        ->and(data_get($product->images, '1.active'))->toBeTrue()
        ->and(data_get($product->images, '1.thumbnail'))->toBeTrue()
        ->and(data_get($product->images, '2.id'))->toBe('existing-image')
        ->and(data_get($product->images, '2.sort_order'))->toBe(10)
        ->and(data_get($product->files, '0.alt'))->toBeNull()
        ->and(data_get($product->files, '0.mimetype'))->toBe('text/plain')
        ->and(data_get($product->files, '0.sort_order'))->toBe(1)
        ->and(data_get($product->files, '0.active'))->toBeTrue()
        ->and(data_get($product->files, '1.alt'))->toBe('Manual PDF')
        ->and(data_get($product->files, '1.mimetype'))->toBe('application/x-custom-pdf')
        ->and(data_get($product->files, '1.sort_order'))->toBe(2)
        ->and(data_get($product->files, '1.active'))->toBeTrue()
        ->and(data_get($product->files, '2.id'))->toBe('existing-file')
        ->and(data_get($product->files, '2.sort_order'))->toBe(10);

    expect(collect($product->images)->pluck('id')->filter()->unique()->count())->toBe(3)
        ->and(collect($product->files)->pluck('id')->filter()->unique()->count())->toBe(3);

    foreach ($product->images as $image) {
        expect($image)->toHaveKeys(['id', 'src', 'alt', 'mimetype', 'sort_order', 'active', 'thumbnail']);

        if (data_get($image, 'id') !== 'existing-image') {
            expect(str_starts_with((string) data_get($image, 'src'), "products/{$product->getKey()}/images/"))->toBeTrue();
            Storage::disk('public')->assertExists((string) data_get($image, 'src'));
        }
    }

    foreach ($product->files as $file) {
        expect($file)->toHaveKeys(['id', 'src', 'alt', 'mimetype', 'sort_order', 'active']);

        if (data_get($file, 'id') !== 'existing-file') {
            expect(str_starts_with((string) data_get($file, 'src'), "products/{$product->getKey()}/files/"))->toBeTrue();
            Storage::disk('public')->assertExists((string) data_get($file, 'src'));
        }
    }
});

it('deletes product media by unique id and removes the file from filesystem when configured', function () {
    Storage::fake('public');

    config()->set('venditio.product.media.delete_files_from_filesystem', true);

    $path = 'products/1/images/delete-me.jpg';
    Storage::disk('public')->put($path, 'content');

    $product = Product::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
        'images' => [
            [
                'id' => 'delete-image',
                'alt' => 'Delete image',
                'mimetype' => 'image/jpeg',
                'sort_order' => 0,
                'active' => true,
                'thumbnail' => false,
                'src' => $path,
            ],
        ],
        'files' => [],
    ]);

    deleteJson(config('venditio.routes.api.v1.prefix') . "/products/{$product->getKey()}/media/delete-image")
        ->assertNoContent();

    $product->refresh();

    expect($product->images)->toBeArray()->toHaveCount(0);
    Storage::disk('public')->assertMissing($path);
});

it('keeps the file on filesystem when media deletion is configured to skip file removal', function () {
    Storage::fake('public');

    config()->set('venditio.product.media.delete_files_from_filesystem', false);

    $path = 'products/1/files/manual.pdf';
    Storage::disk('public')->put($path, 'content');

    $product = Product::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
        'images' => [],
        'files' => [
            [
                'id' => 'delete-file',
                'alt' => 'Delete file',
                'name' => 'manual.pdf',
                'mimetype' => 'application/pdf',
                'sort_order' => 0,
                'active' => true,
                'src' => $path,
            ],
        ],
    ]);

    deleteJson(config('venditio.routes.api.v1.prefix') . "/products/{$product->getKey()}/media/delete-file")
        ->assertNoContent();

    $product->refresh();

    expect($product->files)->toBeArray()->toHaveCount(0);
    Storage::disk('public')->assertExists($path);
});

it('updates existing product media metadata without requiring a new file upload', function () {
    $product = Product::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
        'images' => [
            [
                'id' => 'image-1',
                'alt' => 'Old image alt',
                'name' => 'old-image',
                'mimetype' => 'image/jpeg',
                'sort_order' => 3,
                'active' => true,
                'thumbnail' => false,
                'src' => 'products/image-1.jpg',
            ],
        ],
        'files' => [
            [
                'id' => 'file-1',
                'alt' => 'Old file alt',
                'name' => 'old-file',
                'mimetype' => 'application/pdf',
                'sort_order' => 4,
                'active' => true,
                'src' => 'products/file-1.pdf',
            ],
        ],
    ]);

    patchJson(config('venditio.routes.api.v1.prefix') . "/products/{$product->getKey()}?exclude_active_scope=1", [
        'images' => [
            [
                'id' => 'image-1',
                'alt' => 'Updated image alt',
                'sort_order' => 1,
                'thumbnail' => true,
            ],
        ],
        'files' => [
            [
                'id' => 'file-1',
                'alt' => 'Updated file alt',
                'sort_order' => 2,
                'active' => false,
            ],
        ],
    ])->assertOk()
        ->assertJsonPath('images.0.id', 'image-1')
        ->assertJsonPath('images.0.alt', 'Updated image alt')
        ->assertJsonPath('images.0.sort_order', 1)
        ->assertJsonPath('images.0.thumbnail', true)
        ->assertJsonPath('files.0.id', 'file-1')
        ->assertJsonPath('files.0.alt', 'Updated file alt')
        ->assertJsonPath('files.0.sort_order', 2)
        ->assertJsonPath('files.0.active', false);

    $product->refresh();

    expect(data_get($product->images, '0.src'))->toBe('products/image-1.jpg')
        ->and(data_get($product->images, '0.alt'))->toBe('Updated image alt')
        ->and(data_get($product->images, '0.sort_order'))->toBe(1)
        ->and(data_get($product->images, '0.thumbnail'))->toBeTrue()
        ->and(data_get($product->files, '0.src'))->toBe('products/file-1.pdf')
        ->and(data_get($product->files, '0.alt'))->toBe('Updated file alt')
        ->and(data_get($product->files, '0.sort_order'))->toBe(2)
        ->and(data_get($product->files, '0.active'))->toBeFalse();
});

it('returns product media ordered by sort_order and filters inactive entries by the product active query params', function () {
    $product = Product::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
        'images' => [
            [
                'id' => 'image-a',
                'alt' => 'Hidden image',
                'mimetype' => 'image/jpeg',
                'sort_order' => 3,
                'active' => false,
                'thumbnail' => false,
                'src' => 'products/image-a.jpg',
            ],
            [
                'id' => 'image-b',
                'alt' => 'Visible image',
                'mimetype' => 'image/jpeg',
                'sort_order' => 1,
                'active' => true,
                'thumbnail' => true,
                'src' => 'products/image-b.jpg',
            ],
        ],
        'files' => [
            [
                'id' => 'file-a',
                'alt' => 'Hidden file',
                'name' => 'hidden.pdf',
                'mimetype' => 'application/pdf',
                'sort_order' => 4,
                'active' => false,
                'src' => 'products/file-a.pdf',
            ],
            [
                'id' => 'file-b',
                'alt' => 'Visible file',
                'name' => 'visible.pdf',
                'mimetype' => 'application/pdf',
                'sort_order' => 2,
                'active' => true,
                'src' => 'products/file-b.pdf',
            ],
        ],
    ]);

    getJson(config('venditio.routes.api.v1.prefix') . "/products/{$product->getKey()}")
        ->assertOk()
        ->assertJsonCount(1, 'images')
        ->assertJsonPath('images.0.id', 'image-b')
        ->assertJsonCount(1, 'files')
        ->assertJsonPath('files.0.id', 'file-b');

    getJson(config('venditio.routes.api.v1.prefix') . "/products/{$product->getKey()}?exclude_active_scope=1")
        ->assertOk()
        ->assertJsonPath('images.0.id', 'image-b')
        ->assertJsonPath('images.1.id', 'image-a')
        ->assertJsonPath('files.0.id', 'file-b')
        ->assertJsonPath('files.1.id', 'file-a');

    getJson(config('venditio.routes.api.v1.prefix') . "/products/{$product->getKey()}?is_active=0&exclude_active_scope=1")
        ->assertOk()
        ->assertJsonCount(1, 'images')
        ->assertJsonPath('images.0.id', 'image-a')
        ->assertJsonCount(1, 'files')
        ->assertJsonPath('files.0.id', 'file-a');
});
