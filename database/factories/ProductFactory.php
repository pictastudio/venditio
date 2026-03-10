<?php

namespace PictaStudio\Venditio\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use PictaStudio\Venditio\Enums\{ProductMeasuringUnit, ProductStatus};
use PictaStudio\Venditio\Models\{Brand, Product, ProductType, TaxClass};

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'brand_id' => Brand::factory(),
            // 'product_type_id' => ProductType::factory(),
            'tax_class_id' => TaxClass::factory(),
            'name' => fake()->word(),
            'status' => ProductStatus::Published->value,
            'active' => fake()->boolean(),
            'new' => fake()->boolean(),
            'in_evidence' => fake()->boolean(),
            'sku' => fake()->unique()->isbn13(),
            'ean' => fake()->unique()->ean13(),
            'visible_from' => fake()->dateTimeBetween('-1 year', 'now'),
            'visible_until' => fake()->dateTimeBetween('now', '+1 year'),
            'description' => fake()->paragraph(),
            'description_short' => fake()->sentence(),
            'images' => [
                [
                    'id' => (string) Str::ulid(),
                    'alt' => fake()->sentence(),
                    'mimetype' => 'image/jpeg',
                    'sort_order' => 0,
                    'active' => true,
                    'thumbnail' => true,
                    'src' => fake()->imageUrl(),
                ],
                [
                    'id' => (string) Str::ulid(),
                    'alt' => fake()->sentence(),
                    'mimetype' => 'image/jpeg',
                    'sort_order' => 1,
                    'active' => true,
                    'thumbnail' => false,
                    'src' => fake()->imageUrl(),
                ],
            ],
            'files' => [
                [
                    'id' => (string) Str::ulid(),
                    'name' => fake()->word(),
                    'alt' => fake()->sentence(),
                    'mimetype' => 'application/pdf',
                    'sort_order' => 0,
                    'active' => true,
                    'src' => fake()->url(),
                ],
                [
                    'id' => (string) Str::ulid(),
                    'name' => fake()->word(),
                    'alt' => fake()->sentence(),
                    'mimetype' => 'text/plain',
                    'sort_order' => 1,
                    'active' => true,
                    'src' => fake()->url(),
                ],
            ],
            'measuring_unit' => fake()->randomElement(ProductMeasuringUnit::cases())->value,
            'qty_for_unit' => fake()->optional(0.7)->numberBetween(1, 100),
            'length' => fake()->randomNumber(3),
            'width' => fake()->randomNumber(3),
            'height' => fake()->randomNumber(3),
            'weight' => fake()->randomNumber(3),
            'metadata' => ['keywords' => fake()->words(5)],
        ];
    }

    public function inactive(): self
    {
        return $this->state([
            'active' => false,
        ]);
    }

    public function draft(): self
    {
        return $this->state([
            'status' => ProductStatus::Draft,
        ]);
    }

    public function published(): self
    {
        return $this->state([
            'status' => ProductStatus::Published,
        ]);
    }

    public function archived(): self
    {
        return $this->state([
            'status' => ProductStatus::Archived,
        ]);
    }
}
