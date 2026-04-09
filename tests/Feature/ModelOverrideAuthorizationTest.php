<?php

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PictaStudio\Venditio\Models\Product;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

it('uses the configured product model policy for class-based authorization and route binding', function () {
    config([
        'venditio.authorize_using_policies' => true,
        'venditio.models.product' => TestProductModelOverride::class,
    ]);

    Gate::policy(TestProductModelOverride::class, TestProductModelOverridePolicy::class);

    $product = Product::factory()->create([
        'active' => true,
    ]);
    $prefix = config('venditio.routes.api.v1.prefix');

    getJson($prefix . '/products')->assertForbidden();
    getJson($prefix . '/products/' . $product->getKey())->assertForbidden();
});

class TestProductModelOverride extends Product
{
    protected $table = 'products';
}

class TestProductModelOverridePolicy
{
    public function viewAny(?Authenticatable $user): bool
    {
        return false;
    }

    public function view(?Authenticatable $user, TestProductModelOverride $product): bool
    {
        return false;
    }
}
