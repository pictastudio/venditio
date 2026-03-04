<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PictaStudio\Venditio\Enums\ProductStatus;
use PictaStudio\Venditio\Models\{Product, TaxClass};

use function Pest\Laravel\{patch, patchJson, postJson};

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

it('uploads product images and files on update and persists src and alt', function () {
    Storage::fake('public');

    $product = Product::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
        'images' => null,
        'files' => null,
    ]);

    patch(
        config('venditio.routes.api.v1.prefix') . "/products/{$product->getKey()}",
        [
            'images' => [
                [
                    'file' => UploadedFile::fake()->image('hero.jpg'),
                    'alt' => 'Hero image',
                ],
                [
                    'file' => UploadedFile::fake()->image('gallery.jpg'),
                ],
            ],
            'files' => [
                [
                    'file' => UploadedFile::fake()->create('manual.pdf', 200),
                    'alt' => 'Manual PDF',
                ],
                [
                    'file' => UploadedFile::fake()->create('datasheet.txt', 10),
                ],
            ],
        ],
        ['Accept' => 'application/json']
    )->assertOk();

    $product->refresh();

    expect($product->images)->toBeArray()
        ->and($product->files)->toBeArray()
        ->and(count($product->images))->toBe(2)
        ->and(count($product->files))->toBe(2)
        ->and(data_get($product->images, '0.alt'))->toBe('Hero image')
        ->and(data_get($product->images, '1.alt'))->toBeNull()
        ->and(data_get($product->files, '0.alt'))->toBe('Manual PDF')
        ->and(data_get($product->files, '1.alt'))->toBeNull();

    foreach ($product->images as $image) {
        expect($image)->toHaveKeys(['src', 'alt'])
            ->and(str_starts_with((string) data_get($image, 'src'), "products/{$product->getKey()}/images/"))->toBeTrue();

        Storage::disk('public')->assertExists((string) data_get($image, 'src'));
    }

    foreach ($product->files as $file) {
        expect($file)->toHaveKeys(['src', 'alt'])
            ->and(str_starts_with((string) data_get($file, 'src'), "products/{$product->getKey()}/files/"))->toBeTrue();

        Storage::disk('public')->assertExists((string) data_get($file, 'src'));
    }
});
