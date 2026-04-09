<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PictaStudio\Venditio\Http\Controllers\Api\Controller;
use PictaStudio\Venditio\Models\Brand;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('checks authorization using the registered policy when authorize_using_policies is true', function () {
    config(['venditio.authorize_using_policies' => true]);

    Gate::policy(Brand::class, TestBrandPolicyDenyView::class);

    $user = new GenericUser(['id' => 1]);
    $brand = Brand::factory()->create();

    actingAs($user);

    $controller = controllerThatAuthorizes();

    $controller->testAuthorize('view', $brand);
})->throws(AuthorizationException::class)->group('policy');

it('does not throw when policy allows and authorize_using_policies is true', function () {
    config(['venditio.authorize_using_policies' => true]);

    Gate::policy(Brand::class, TestBrandPolicyAllowAll::class);

    $user = new GenericUser(['id' => 1]);
    $brand = Brand::factory()->create();

    actingAs($user);

    $controller = controllerThatAuthorizes();

    $controller->testAuthorize('view', $brand);

    expect(true)->toBeTrue();
})->group('policy');

it('checks authorization for guests when authorize_using_policies is true', function () {
    config(['venditio.authorize_using_policies' => true]);

    Gate::policy(Brand::class, TestBrandPolicyDenyGuestViewAny::class);

    $controller = controllerThatAuthorizes();

    $controller->testAuthorize('viewAny', Brand::class);
})->throws(AuthorizationException::class)->group('policy');

it('does not check authorization when authorize_using_policies is false', function () {
    config(['venditio.authorize_using_policies' => false]);

    Gate::policy(Brand::class, TestBrandPolicyDenyView::class);

    $user = new GenericUser(['id' => 1]);
    $brand = Brand::factory()->create();

    actingAs($user);

    $controller = controllerThatAuthorizes();

    $controller->testAuthorize('view', $brand);

    expect(true)->toBeTrue();
})->group('policy');

it('resolves policy for model from config', function () {
    config(['venditio.authorize_using_policies' => true]);

    Gate::policy(Brand::class, TestBrandPolicyAllowAll::class);

    expect(Gate::getPolicyFor(Brand::class))->toBeInstanceOf(TestBrandPolicyAllowAll::class);
})->group('policy');

function controllerThatAuthorizes(): object
{
    return new class extends Controller
    {
        public function testAuthorize(string $ability, mixed $arguments): void
        {
            $this->authorizeIfConfigured($ability, $arguments);
        }
    };
}

class TestBrandPolicyDenyView
{
    public function view(Authenticatable $user, Brand $brand): bool
    {
        return false;
    }

    public function viewAny(?Authenticatable $user): bool
    {
        return true;
    }
}

class TestBrandPolicyAllowAll
{
    public function view(Authenticatable $user, Brand $brand): bool
    {
        return true;
    }

    public function viewAny(?Authenticatable $user): bool
    {
        return true;
    }
}

class TestBrandPolicyDenyGuestViewAny
{
    public function viewAny(?Authenticatable $user): bool
    {
        return false;
    }
}
