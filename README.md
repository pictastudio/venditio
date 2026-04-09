# Venditio Ecommerce

[![Latest Version on Packagist](https://img.shields.io/packagist/v/pictastudio/venditio.svg?style=flat-square)](https://packagist.org/packages/pictastudio/venditio)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/pictastudio/venditio/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/pictastudio/venditio/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/pictastudio/venditio/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/pictastudio/venditio/actions?query=workflow%3A)
[![Total Downloads](https://img.shields.io/packagist/dt/pictastudio/venditio.svg?style=flat-square)](https://packagist.org/packages/pictastudio/venditio)

Venditio is a headless ecommerce package for Laravel.
It provides API-only ecommerce primitives while host applications own auth, frontend, and rendering.
Products can be organized through brands, categories, tags, and flat product collections.

## Installation

```bash
composer require pictastudio/venditio
```

### Install Venditio

```bash
php artisan venditio:install
```

## Documentation

- Architecture: `docs/ARCHITECTURE.md`
- API reference: `docs/API.md`
- Database schema (DBML): `database.dbml`

## Product Variants Model

Venditio models variants using a parent/child product strategy:

- A base product is a row in `products` with `parent_id = null`
- Each purchasable variant is another row in `products` with `parent_id` set to the base product id
- Variant axes live in `product_variants` (for example `Color`, `Size`)
- Axis values live in `product_variant_options` (for example `Red`, `M`)
- Assigned option values for each variant product are stored in `product_configuration`

This keeps a single product identity while still allowing independent SKU/inventory/pricing per concrete variant.

## Configuration

All behavior is configured through `config/venditio.php`.

### Key sections

- `routes.api`: route enable/prefix/name/middleware/pagination and resource wrapping
- `models`: model overrides (all package models are replaceable)
- `validations`: validation contract to implementation bindings
- `authorize_using_policies`: optional policy/gate authorization
- `price_lists`: optional multi-price feature
- `discounts`: discount calculator/bindings/rules configuration
- `product`: product enums, sku generator and product list variant visibility defaults
- `product_variants`: variant naming/copy behavior

### User model and auth integration

Authentication is not enforced by default.
If your host app uses Sanctum, add `HasApiTokens` to your user model and point the package user model config to it:

```php
namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use PictaStudio\Venditio\Models\User as VenditioUser;

class User extends VenditioUser
{
    use HasApiTokens;
}
```

```php
'models' => [
    // ...
    'user' => App\Models\User::class,
],
```

### Optional policy integration

Register policies in the host app and keep `venditio.authorize_using_policies` enabled:

```php
use App\Models\Product;
use App\Policies\ProductPolicy;
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    Gate::policy(Product::class, ProductPolicy::class);
}
```

Controllers call authorization only when enabled and when a policy/gate definition exists.

### Validation customization

Validation rules are resolved from contracts in `config('venditio.validations')`.
Override a resource by rebinding its contract to your implementation.

```php
use App\Validations\AddressValidation;
use PictaStudio\Venditio\Validations\Contracts\AddressValidationRules;

public function boot(): void
{
    $this->app->singleton(AddressValidationRules::class, AddressValidation::class);
}
```

### Identifier generator customization

```php
use PictaStudio\Venditio\Contracts\CartIdentifierGeneratorInterface;
use PictaStudio\Venditio\Contracts\OrderIdentifierGeneratorInterface;

$this->app->singleton(CartIdentifierGeneratorInterface::class, App\Generators\CartIdentifierGenerator::class);
$this->app->singleton(OrderIdentifierGeneratorInterface::class, App\Generators\OrderIdentifierGenerator::class);
```

## Commands

### Release stock for abandoned carts

Enabled by default and configurable from:

- `venditio.commands.release_stock_for_abandoned_carts.enabled`
- `venditio.commands.release_stock_for_abandoned_carts.inactive_for_minutes`
- `venditio.commands.release_stock_for_abandoned_carts.schedule_every_minutes`

### Publish Bruno collection

```bash
php artisan vendor:publish --tag=venditio-bruno
```

## High-level structure

```text
src/
|--- Actions
|--- Contracts
|--- Discounts
|--- Dto
|--- Enums
|--- Http
|--- Models
|--- Pipelines
|--- Pricing
|--- Validations
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Picta Studio](https://github.com/pictastudio)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
