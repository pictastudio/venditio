<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use PictaStudio\Venditio\Contracts\ProductSkuGeneratorInterface;
use PictaStudio\Venditio\Models\{Brand, Product, ProductCollection, ProductType, ProductVariant, ProductVariantOption, TaxClass};

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

it('generates product variants from variant options', function () {
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
        'name' => 'red',
    ]);
    $blue = ProductVariantOption::factory()->create([
        'product_variant_id' => $colorVariant->getKey(),
        'name' => 'blue',
    ]);
    $small = ProductVariantOption::factory()->create([
        'product_variant_id' => $sizeVariant->getKey(),
        'name' => 's',
    ]);
    $large = ProductVariantOption::factory()->create([
        'product_variant_id' => $sizeVariant->getKey(),
        'name' => 'l',
    ]);

    $product = Product::factory()->create([
        'brand_id' => $brand->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'product_type_id' => $productType->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $response = postJson(config('venditio.routes.api.v1.prefix') . "/products/{$product->getKey()}/variants", [
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
    ])->assertCreated();

    expect($response->json('meta.created'))->toBe(4);
    expect(Product::query()->where('parent_id', $product->getKey())->count())->toBe(4);

    $variant = Product::query()->where('parent_id', $product->getKey())->first();
    expect($variant->variantOptions()->count())->toBe(2);
});

it('skips existing variant combinations', function () {
    $brand = Brand::factory()->create();
    $taxClass = TaxClass::factory()->create();
    $productType = ProductType::factory()->create();

    $colorVariant = ProductVariant::factory()->create([
        'product_type_id' => $productType->getKey(),
        'name' => 'Color',
    ]);
    $red = ProductVariantOption::factory()->create([
        'product_variant_id' => $colorVariant->getKey(),
        'name' => 'red',
    ]);
    $blue = ProductVariantOption::factory()->create([
        'product_variant_id' => $colorVariant->getKey(),
        'name' => 'blue',
    ]);

    $product = Product::factory()->create([
        'brand_id' => $brand->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'product_type_id' => $productType->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    postJson(config('venditio.routes.api.v1.prefix') . "/products/{$product->getKey()}/variants", [
        'variants' => [
            [
                'variant_id' => $colorVariant->getKey(),
                'option_ids' => [$red->getKey(), $blue->getKey()],
            ],
        ],
    ])->assertCreated();

    $response = postJson(config('venditio.routes.api.v1.prefix') . "/products/{$product->getKey()}/variants", [
        'variants' => [
            [
                'variant_id' => $colorVariant->getKey(),
                'option_ids' => [$red->getKey(), $blue->getKey()],
            ],
        ],
    ])->assertCreated();

    expect($response->json('meta.created'))->toBe(0);
    expect($response->json('meta.skipped'))->toBe(2);
});

it('creates only new combinations when adding options later', function () {
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
        'name' => 'red',
    ]);
    $blue = ProductVariantOption::factory()->create([
        'product_variant_id' => $colorVariant->getKey(),
        'name' => 'blue',
    ]);
    $small = ProductVariantOption::factory()->create([
        'product_variant_id' => $sizeVariant->getKey(),
        'name' => 's',
    ]);
    $medium = ProductVariantOption::factory()->create([
        'product_variant_id' => $sizeVariant->getKey(),
        'name' => 'm',
    ]);

    $product = Product::factory()->create([
        'brand_id' => $brand->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'product_type_id' => $productType->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    postJson(config('venditio.routes.api.v1.prefix') . "/products/{$product->getKey()}/variants", [
        'variants' => [
            [
                'variant_id' => $colorVariant->getKey(),
                'option_ids' => [$red->getKey(), $blue->getKey()],
            ],
            [
                'variant_id' => $sizeVariant->getKey(),
                'option_ids' => [$small->getKey()],
            ],
        ],
    ])->assertCreated();

    expect(Product::query()->where('parent_id', $product->getKey())->count())->toBe(2);

    $large = ProductVariantOption::factory()->create([
        'product_variant_id' => $sizeVariant->getKey(),
        'name' => 'l',
    ]);

    $response = postJson(config('venditio.routes.api.v1.prefix') . "/products/{$product->getKey()}/variants", [
        'variants' => [
            [
                'variant_id' => $colorVariant->getKey(),
                'option_ids' => [$red->getKey(), $blue->getKey()],
            ],
            [
                'variant_id' => $sizeVariant->getKey(),
                'option_ids' => [$small->getKey(), $medium->getKey(), $large->getKey()],
            ],
        ],
    ])->assertCreated();

    expect($response->json('meta.created'))->toBe(4);
    expect($response->json('meta.skipped'))->toBe(2);
    expect(Product::query()->where('parent_id', $product->getKey())->count())->toBe(6);

    $signatures = Product::query()
        ->withoutGlobalScopes()
        ->where('parent_id', $product->getKey())
        ->with('variantOptions:id')
        ->get()
        ->map(fn (Product $variant) => (
            $variant->variantOptions->pluck('id')->sort()->implode('-')
        ));

    $expectedSignature = collect([$red->getKey(), $small->getKey()])->sort()->implode('-');

    expect($signatures->filter(fn (string $signature) => $signature === $expectedSignature))
        ->toHaveCount(1);
});

it('copies product collections to generated variants by default', function () {
    $brand = Brand::factory()->create();
    $taxClass = TaxClass::factory()->create();
    $productType = ProductType::factory()->create();
    $collection = ProductCollection::factory()->create();

    $colorVariant = ProductVariant::factory()->create([
        'product_type_id' => $productType->getKey(),
        'name' => 'Color',
    ]);
    $red = ProductVariantOption::factory()->create([
        'product_variant_id' => $colorVariant->getKey(),
        'name' => 'red',
    ]);

    $product = Product::factory()->create([
        'brand_id' => $brand->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'product_type_id' => $productType->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $product->collections()->sync([$collection->getKey()]);

    postJson(config('venditio.routes.api.v1.prefix') . "/products/{$product->getKey()}/variants", [
        'variants' => [
            [
                'variant_id' => $colorVariant->getKey(),
                'option_ids' => [$red->getKey()],
            ],
        ],
    ])->assertCreated();

    $variant = Product::query()
        ->withoutGlobalScopes()
        ->where('parent_id', $product->getKey())
        ->firstOrFail();

    expect($variant->collections()->get()->pluck('id')->all())
        ->toBe([$collection->getKey()]);
});

it('rejects variant generation when product has no type', function () {
    $brand = Brand::factory()->create();
    $taxClass = TaxClass::factory()->create();

    $productType = ProductType::factory()->create();
    $variant = ProductVariant::factory()->create([
        'product_type_id' => $productType->getKey(),
    ]);
    $option = ProductVariantOption::factory()->create([
        'product_variant_id' => $variant->getKey(),
    ]);

    $product = Product::factory()->create([
        'brand_id' => $brand->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'product_type_id' => null,
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    postJson(config('venditio.routes.api.v1.prefix') . "/products/{$product->getKey()}/variants", [
        'variants' => [
            [
                'variant_id' => $variant->getKey(),
                'option_ids' => [$option->getKey()],
            ],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['product_type_id']);
});

it('rejects variant generation for a variant product', function () {
    $brand = Brand::factory()->create();
    $taxClass = TaxClass::factory()->create();
    $productType = ProductType::factory()->create();

    $variant = ProductVariant::factory()->create([
        'product_type_id' => $productType->getKey(),
    ]);
    $option = ProductVariantOption::factory()->create([
        'product_variant_id' => $variant->getKey(),
    ]);

    $baseProduct = Product::factory()->create([
        'brand_id' => $brand->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'product_type_id' => $productType->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $variantProduct = Product::factory()->create([
        'brand_id' => $brand->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'product_type_id' => $productType->getKey(),
        'parent_id' => $baseProduct->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    postJson(config('venditio.routes.api.v1.prefix') . "/products/{$variantProduct->getKey()}/variants", [
        'variants' => [
            [
                'variant_id' => $variant->getKey(),
                'option_ids' => [$option->getKey()],
            ],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['product']);
});

it('rejects variants that do not belong to the product type', function () {
    $brand = Brand::factory()->create();
    $taxClass = TaxClass::factory()->create();
    $productType = ProductType::factory()->create();
    $otherType = ProductType::factory()->create();

    $variant = ProductVariant::factory()->create([
        'product_type_id' => $otherType->getKey(),
    ]);
    $option = ProductVariantOption::factory()->create([
        'product_variant_id' => $variant->getKey(),
    ]);

    $product = Product::factory()->create([
        'brand_id' => $brand->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'product_type_id' => $productType->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    postJson(config('venditio.routes.api.v1.prefix') . "/products/{$product->getKey()}/variants", [
        'variants' => [
            [
                'variant_id' => $variant->getKey(),
                'option_ids' => [$option->getKey()],
            ],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['variants']);
});

it('rejects options that do not belong to their variant', function () {
    $brand = Brand::factory()->create();
    $taxClass = TaxClass::factory()->create();
    $productType = ProductType::factory()->create();

    $variant = ProductVariant::factory()->create([
        'product_type_id' => $productType->getKey(),
    ]);
    $otherVariant = ProductVariant::factory()->create([
        'product_type_id' => $productType->getKey(),
    ]);
    $option = ProductVariantOption::factory()->create([
        'product_variant_id' => $otherVariant->getKey(),
    ]);

    $product = Product::factory()->create([
        'brand_id' => $brand->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'product_type_id' => $productType->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    postJson(config('venditio.routes.api.v1.prefix') . "/products/{$product->getKey()}/variants", [
        'variants' => [
            [
                'variant_id' => $variant->getKey(),
                'option_ids' => [$option->getKey()],
            ],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['variants']);
});

it('rejects duplicate variant ids in the payload', function () {
    $brand = Brand::factory()->create();
    $taxClass = TaxClass::factory()->create();
    $productType = ProductType::factory()->create();

    $variant = ProductVariant::factory()->create([
        'product_type_id' => $productType->getKey(),
    ]);
    $option = ProductVariantOption::factory()->create([
        'product_variant_id' => $variant->getKey(),
    ]);

    $product = Product::factory()->create([
        'brand_id' => $brand->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'product_type_id' => $productType->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    postJson(config('venditio.routes.api.v1.prefix') . "/products/{$product->getKey()}/variants", [
        'variants' => [
            [
                'variant_id' => $variant->getKey(),
                'option_ids' => [$option->getKey()],
            ],
            [
                'variant_id' => $variant->getKey(),
                'option_ids' => [$option->getKey()],
            ],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['variants']);
});

it('uses a custom product sku generator when creating variants', function () {
    app()->singleton(ProductSkuGeneratorInterface::class, TestProductSkuGenerator::class);

    $brand = Brand::factory()->create();
    $taxClass = TaxClass::factory()->create();
    $productType = ProductType::factory()->create();

    $colorVariant = ProductVariant::factory()->create([
        'product_type_id' => $productType->getKey(),
        'name' => 'Color',
    ]);
    $red = ProductVariantOption::factory()->create([
        'product_variant_id' => $colorVariant->getKey(),
        'name' => 'red',
    ]);

    $product = Product::factory()->create([
        'brand_id' => $brand->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'product_type_id' => $productType->getKey(),
        'sku' => 'BASE-SKU',
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    postJson(config('venditio.routes.api.v1.prefix') . "/products/{$product->getKey()}/variants", [
        'variants' => [
            [
                'variant_id' => $colorVariant->getKey(),
                'option_ids' => [$red->getKey()],
            ],
        ],
    ])->assertCreated();

    $variant = Product::query()
        ->withoutGlobalScopes()
        ->where('parent_id', $product->getKey())
        ->firstOrFail();

    expect($variant->sku)->toBe('CUSTOM-VARIANT-' . $product->getKey());
});

class TestProductSkuGenerator implements ProductSkuGeneratorInterface
{
    public function forProductPayload(array $payload): string
    {
        return 'CUSTOM-PRODUCT-SKU';
    }

    public function forVariant(Product $baseProduct, array $options): string
    {
        return 'CUSTOM-VARIANT-' . $baseProduct->getKey();
    }
}
