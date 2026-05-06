<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PictaStudio\Venditio\Models\{Brand, Product, ProductType, ProductVariant, ProductVariantOption, TaxClass};

use function Pest\Laravel\{deleteJson, getJson, patchJson, post};

uses(RefreshDatabase::class);

it('uploads shared media for a variant option and appends it to matching variant products only', function () {
    Storage::fake('public');

    $brand = Brand::factory()->create();
    $taxClass = TaxClass::factory()->create();
    $productType = ProductType::factory()->create();

    $colorVariant = ProductVariant::factory()->create([
        'product_type_id' => $productType->getKey(),
        'name' => 'Color',
    ]);
    $sizeVariant = ProductVariant::factory()->create([
        'product_type_id' => $productType->getKey(),
        'name' => 'Size',
    ]);

    $red = ProductVariantOption::factory()->create([
        'product_variant_id' => $colorVariant->getKey(),
        'name' => 'Red',
    ]);
    $blue = ProductVariantOption::factory()->create([
        'product_variant_id' => $colorVariant->getKey(),
        'name' => 'Blue',
    ]);
    $small = ProductVariantOption::factory()->create([
        'product_variant_id' => $sizeVariant->getKey(),
        'name' => 'S',
    ]);
    $large = ProductVariantOption::factory()->create([
        'product_variant_id' => $sizeVariant->getKey(),
        'name' => 'L',
    ]);

    $product = Product::factory()->create([
        'brand_id' => $brand->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'product_type_id' => $productType->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
        'images' => [],
        'files' => [],
    ]);

    post(config('venditio.routes.api.v1.prefix') . "/products/{$product->getKey()}/variants", [
        'variants' => [
            [
                'variant_id' => $colorVariant->getKey(),
                'option_ids' => [$red->getKey(), $blue->getKey()],
            ],
            [
                'variant_id' => $sizeVariant->getKey(),
                'option_ids' => [$small->getKey(), $large->getKey()],
            ],
        ],
    ], ['Accept' => 'application/json'])->assertCreated();

    $redVariants = Product::withoutGlobalScopes()
        ->where('parent_id', $product->getKey())
        ->whereHas('variantOptions', fn ($builder) => $builder->whereKey($red->getKey()))
        ->get();
    $blueVariants = Product::withoutGlobalScopes()
        ->where('parent_id', $product->getKey())
        ->whereHas('variantOptions', fn ($builder) => $builder->whereKey($blue->getKey()))
        ->get();

    $redVariants->each(function (Product $variant): void {
        $variant->forceFill([
            'images' => [[
                'id' => "specific-image-{$variant->getKey()}",
                'alt' => 'Specific image',
                'mimetype' => 'image/jpeg',
                'sort_order' => 0,
                'active' => true,
                'thumbnail' => false,
                'shared_from_variant_option' => false,
                'src' => "products/{$variant->getKey()}/specific-image.jpg",
            ]],
            'files' => [[
                'id' => "specific-file-{$variant->getKey()}",
                'alt' => 'Specific file',
                'name' => 'specific.pdf',
                'mimetype' => 'application/pdf',
                'sort_order' => 0,
                'active' => true,
                'shared_from_variant_option' => false,
                'src' => "products/{$variant->getKey()}/specific-file.pdf",
            ]],
        ])->save();
    });

    post(
        config('venditio.routes.api.v1.prefix') . "/product/{$product->getKey()}/{$red->getKey()}/upload",
        [
            'images' => [
                [
                    'file' => UploadedFile::fake()->image('red-front.jpg'),
                    'alt' => 'Red front',
                    'thumbnail' => true,
                ],
            ],
            'files' => [
                [
                    'file' => UploadedFile::fake()->create('red-manual.pdf', 100, 'application/pdf'),
                    'alt' => 'Red manual',
                ],
            ],
        ],
        ['Accept' => 'application/json']
    )->assertOk()
        ->assertJsonPath('meta.updated', $redVariants->count())
        ->assertJsonPath('data.0.images.0.shared_from_variant_option', false)
        ->assertJsonPath('data.0.images.1.shared_from_variant_option', true)
        ->assertJsonPath('data.0.files.0.shared_from_variant_option', false)
        ->assertJsonPath('data.0.files.1.shared_from_variant_option', true);

    foreach ($redVariants as $variant) {
        $variant->refresh();

        expect($variant->images)->toHaveCount(2)
            ->and(data_get($variant->images, '0.shared_from_variant_option'))->toBeFalse()
            ->and(data_get($variant->images, '1.shared_from_variant_option'))->toBeTrue()
            ->and(data_get($variant->images, '1.thumbnail'))->toBeTrue()
            ->and((string) data_get($variant->images, '1.src'))->toStartWith("products/{$product->getKey()}/variant_options/{$red->getKey()}/images/")
            ->and($variant->files)->toHaveCount(2)
            ->and(data_get($variant->files, '0.shared_from_variant_option'))->toBeFalse()
            ->and(data_get($variant->files, '1.shared_from_variant_option'))->toBeTrue()
            ->and((string) data_get($variant->files, '1.src'))->toStartWith("products/{$product->getKey()}/variant_options/{$red->getKey()}/files/");

        Storage::disk('public')->assertExists((string) data_get($variant->images, '1.src'));
        Storage::disk('public')->assertExists((string) data_get($variant->files, '1.src'));
    }

    foreach ($blueVariants as $variant) {
        $variant->refresh();

        expect($variant->images)->toBeArray()->toHaveCount(0)
            ->and($variant->files)->toBeArray()->toHaveCount(0);
    }

    $optionResponse = getJson(config('venditio.routes.api.v1.prefix') . "/product_variant_options/{$red->getKey()}")
        ->assertOk()
        ->assertJsonMissingPath('image')
        ->assertJsonCount(1, 'images')
        ->assertJsonPath('images.0.alt', 'Red front')
        ->assertJsonPath('images.0.shared_from_variant_option', true);

    expect((string) data_get($optionResponse->json(), 'images.0.src'))
        ->toContain("/storage/products/{$product->getKey()}/variant_options/{$red->getKey()}/images/");
});

it('rejects shared variant option media upload when the option does not match the target product', function () {
    Storage::fake('public');

    $productType = ProductType::factory()->create();
    $otherProductType = ProductType::factory()->create();

    $product = Product::factory()->create([
        'product_type_id' => $productType->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $option = ProductVariantOption::factory()->create([
        'product_variant_id' => ProductVariant::factory()->create([
            'product_type_id' => $otherProductType->getKey(),
        ])->getKey(),
    ]);

    post(
        config('venditio.routes.api.v1.prefix') . "/product/{$product->getKey()}/{$option->getKey()}/upload",
        [
            'images' => [
                [
                    'file' => UploadedFile::fake()->image('not-matching.jpg'),
                ],
            ],
        ],
        ['Accept' => 'application/json']
    )->assertUnprocessable()
        ->assertJsonValidationErrors(['product_variant_option_id']);
});

it('propagates shared variant option image metadata updates to matching media copies', function () {
    $brand = Brand::factory()->create();
    $taxClass = TaxClass::factory()->create();
    $productType = ProductType::factory()->create(['active' => true]);

    $colorVariant = ProductVariant::factory()->create([
        'product_type_id' => $productType->getKey(),
        'name' => 'Color',
    ]);
    $sizeVariant = ProductVariant::factory()->create([
        'product_type_id' => $productType->getKey(),
        'name' => 'Size',
    ]);

    $red = ProductVariantOption::factory()->create([
        'product_variant_id' => $colorVariant->getKey(),
        'name' => 'Red',
    ]);
    $small = ProductVariantOption::factory()->create([
        'product_variant_id' => $sizeVariant->getKey(),
        'name' => 'S',
    ]);
    $large = ProductVariantOption::factory()->create([
        'product_variant_id' => $sizeVariant->getKey(),
        'name' => 'L',
    ]);

    $product = Product::factory()->create([
        'brand_id' => $brand->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'product_type_id' => $productType->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $sharedSrc = "products/{$product->getKey()}/variant_options/{$red->getKey()}/images/red.jpg";

    $firstVariant = Product::factory()->create([
        'brand_id' => $brand->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'product_type_id' => $productType->getKey(),
        'parent_id' => $product->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $firstVariant->variantOptions()->sync([$red->getKey(), $small->getKey()]);
    $firstVariant->forceFill([
        'images' => [[
            'id' => 'first-copy',
            'name' => 'Old name',
            'alt' => 'Old alt',
            'mimetype' => 'image/jpeg',
            'sort_order' => 5,
            'active' => true,
            'thumbnail' => false,
            'shared_from_variant_option' => true,
            'src' => $sharedSrc,
        ]],
    ])->save();

    $secondVariant = Product::factory()->create([
        'brand_id' => $brand->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'product_type_id' => $productType->getKey(),
        'parent_id' => $product->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $secondVariant->variantOptions()->sync([$red->getKey(), $large->getKey()]);
    $secondVariant->forceFill([
        'images' => [[
            'id' => 'second-copy',
            'name' => 'Old name',
            'alt' => 'Old alt',
            'mimetype' => 'image/jpeg',
            'sort_order' => 5,
            'active' => true,
            'thumbnail' => false,
            'shared_from_variant_option' => true,
            'src' => $sharedSrc,
        ]],
    ])->save();

    patchJson(config('venditio.routes.api.v1.prefix') . "/products/{$firstVariant->getKey()}", [
        'images' => [[
            'id' => 'first-copy',
            'name' => 'Red front',
            'alt' => 'Updated red front',
            'sort_order' => 1,
            'thumbnail' => true,
        ]],
    ])->assertOk()
        ->assertJsonPath('images.0.name', 'Red front')
        ->assertJsonPath('images.0.alt', 'Updated red front')
        ->assertJsonPath('images.0.sort_order', 1);

    $firstVariant->refresh();
    $secondVariant->refresh();

    expect(data_get($firstVariant->images, '0.name'))->toBe('Red front')
        ->and(data_get($firstVariant->images, '0.alt'))->toBe('Updated red front')
        ->and(data_get($firstVariant->images, '0.sort_order'))->toBe(1)
        ->and(data_get($firstVariant->images, '0.thumbnail'))->toBeTrue()
        ->and(data_get($secondVariant->images, '0.name'))->toBe('Red front')
        ->and(data_get($secondVariant->images, '0.alt'))->toBe('Updated red front')
        ->and(data_get($secondVariant->images, '0.sort_order'))->toBe(1)
        ->and(data_get($secondVariant->images, '0.thumbnail'))->toBeTrue();

    getJson(config('venditio.routes.api.v1.prefix') . "/products/{$product->getKey()}?include=variants,variants_options_table")
        ->assertOk()
        ->assertJsonFragment([
            'name' => 'Red front',
            'alt' => 'Updated red front',
            'sort_order' => 1,
        ]);
});

it('keeps the shared file on disk while another matching product still references it', function () {
    Storage::fake('public');

    $brand = Brand::factory()->create();
    $taxClass = TaxClass::factory()->create();
    $productType = ProductType::factory()->create();

    $colorVariant = ProductVariant::factory()->create([
        'product_type_id' => $productType->getKey(),
        'name' => 'Color',
    ]);
    $sizeVariant = ProductVariant::factory()->create([
        'product_type_id' => $productType->getKey(),
        'name' => 'Size',
    ]);

    $red = ProductVariantOption::factory()->create([
        'product_variant_id' => $colorVariant->getKey(),
        'name' => 'Red',
    ]);
    $small = ProductVariantOption::factory()->create([
        'product_variant_id' => $sizeVariant->getKey(),
        'name' => 'S',
    ]);
    $large = ProductVariantOption::factory()->create([
        'product_variant_id' => $sizeVariant->getKey(),
        'name' => 'L',
    ]);

    $product = Product::factory()->create([
        'brand_id' => $brand->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'product_type_id' => $productType->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
        'images' => [],
        'files' => [],
    ]);

    post(config('venditio.routes.api.v1.prefix') . "/products/{$product->getKey()}/variants", [
        'variants' => [
            [
                'variant_id' => $colorVariant->getKey(),
                'option_ids' => [$red->getKey()],
            ],
            [
                'variant_id' => $sizeVariant->getKey(),
                'option_ids' => [$small->getKey(), $large->getKey()],
            ],
        ],
    ], ['Accept' => 'application/json'])->assertCreated();

    post(
        config('venditio.routes.api.v1.prefix') . "/product/{$product->getKey()}/{$red->getKey()}/upload",
        [
            'images' => [
                [
                    'file' => UploadedFile::fake()->image('red-shared.jpg'),
                ],
            ],
        ],
        ['Accept' => 'application/json']
    )->assertOk();

    $matchingVariants = Product::withoutGlobalScopes()
        ->where('parent_id', $product->getKey())
        ->whereHas('variantOptions', fn ($builder) => $builder->whereKey($red->getKey()))
        ->orderBy('id')
        ->get();

    $firstVariant = $matchingVariants->first();
    $secondVariant = $matchingVariants->skip(1)->first();

    expect($firstVariant)->not->toBeNull()
        ->and($secondVariant)->not->toBeNull();

    $sharedMediaId = data_get($firstVariant, 'images.0.id');
    $sharedPath = data_get($firstVariant, 'images.0.src');

    deleteJson(config('venditio.routes.api.v1.prefix') . "/products/{$firstVariant->getKey()}/media/{$sharedMediaId}")
        ->assertNoContent();

    Storage::disk('public')->assertExists((string) $sharedPath);

    $secondVariant->refresh();

    expect(data_get($secondVariant, 'images.0.src'))->toBe($sharedPath);
});
