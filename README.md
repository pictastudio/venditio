# Venditio Ecommerce

[![Latest Version on Packagist](https://img.shields.io/packagist/v/pictastudio/venditio.svg?style=flat-square)](https://packagist.org/packages/pictastudio/venditio)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/pictastudio/venditio/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/pictastudio/venditio/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/pictastudio/venditio/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/pictastudio/venditio/actions?query=workflow%3A)
[![Total Downloads](https://img.shields.io/packagist/dt/pictastudio/venditio.svg?style=flat-square)](https://packagist.org/packages/pictastudio/venditio)

Venditio is a headless ecommerce package for Laravel.
It provides API-only ecommerce primitives while host applications own auth, frontend, and rendering.
Products can be organized through brands, categories, tags, and flat product collections.
Orders can also expose configurable return reasons and return requests with per-line derived return state.

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
- `shipping`: shipping strategy, default volumetric divisor, and resolver bindings
- `product`: product enums, sku generator and product list variant visibility defaults
- `product_variants`: variant naming/copy behavior
- `invoices`: optional persisted invoice generation and swappable PDF pipeline

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
use PictaStudio\Venditio\Contracts\InvoiceNumberGeneratorInterface;
use PictaStudio\Venditio\Contracts\OrderIdentifierGeneratorInterface;

$this->app->singleton(CartIdentifierGeneratorInterface::class, App\Generators\CartIdentifierGenerator::class);
$this->app->singleton(InvoiceNumberGeneratorInterface::class, App\Generators\InvoiceNumberGenerator::class);
$this->app->singleton(OrderIdentifierGeneratorInterface::class, App\Generators\OrderIdentifierGenerator::class);
```

## Invoices

Venditio can persist one immutable invoice document per order and render a PDF from the stored snapshot.
The feature is disabled by default and stays API-only: host apps decide when to create an invoice and can replace the default number generator, payload factory, HTML template, or PDF renderer.

Enable it in `config/venditio.php`:

```php
'invoices' => [
    'enabled' => true,
    'seller' => [
        'name' => 'Acme SRL',
        'address_line_1' => 'Via Roma 1',
        'city' => 'Verona',
        'postal_code' => '37100',
        'country' => 'Italy',
    ],
],
```

Default endpoints:

- `POST /orders/{order}/invoice`
- `GET /orders/{order}/invoice`
- `GET /orders/{order}/invoice/pdf`

The generated invoice record stores seller data, billing/shipping addresses, lines, totals, payments, and rendered HTML so later order edits do not rewrite already issued documents.

### Invoice customization

```php
'invoices' => [
    'number_generator' => App\Invoices\CustomInvoiceNumberGenerator::class,
    'payload_factory' => App\Invoices\CustomInvoicePayloadFactory::class,
    'template' => App\Invoices\CustomInvoiceTemplate::class,
    'renderer' => App\Invoices\CustomInvoicePdfRenderer::class,
],
```

Relevant contracts:

- `PictaStudio\Venditio\Contracts\InvoiceNumberGeneratorInterface`
- `PictaStudio\Venditio\Contracts\InvoicePayloadFactoryInterface`
- `PictaStudio\Venditio\Contracts\InvoiceTemplateInterface`
- `PictaStudio\Venditio\Contracts\InvoicePdfRendererInterface`

## Shipping

Venditio ships with an API-first shipping domain built around three resources:

- `shipping_methods`: couriers or delivery methods, with `flat_fee` and `volumetric_divisor`
- `shipping_zones`: geographic scopes, linked to countries, regions, and provinces
- `shipping_method_zones`: the priced pivot between a method and a zone, with `rate_tiers` and `over_weight_price_per_kg`

Shipping behavior is controlled by `venditio.shipping.strategy`:

- `disabled`: shipping fee is always `0`, but weights are still calculated
- `flat`: the cart uses `shipping_methods.flat_fee`
- `zones`: the cart resolves the best matching zone for the selected method and calculates the fee from the pivot row

The default volumetric divisor is `5000`, configurable through `venditio.shipping.default_volumetric_divisor`.
Each shipping method can override it with its own `volumetric_divisor`, so different couriers can use different volumetric rules.

### How shipping is calculated

On cart create and update, Venditio calculates shipping after line totals and before cart-level discounts.

1. The cart resolves the selected `shipping_method_id`.
2. It calculates the line weights from `cart.lines[*].product_data`.
3. It resolves the destination from `addresses.shipping`.
4. If the strategy is `zones`, it finds the best active zone linked to the selected shipping method.
5. It calculates the shipping fee.
6. Discounts run after that, so `free_shipping` can still zero the final `shipping_fee`.
7. When an order is created from a cart, Venditio snapshots `shipping_method_id`, `shipping_zone_id`, weights, fee, `shipping_method_data`, and `shipping_zone_data` on the order.

Weight calculation uses these formulas:

- `specific_weight = sum(product_data.weight * qty)`
- `volumetric_weight = sum((length * width * height / divisor) * qty)`
- `chargeable_weight = max(specific_weight, volumetric_weight)`

Expected units in the default implementation:

- `weight` in `kg`
- `length`, `width`, `height` in `cm`

Destination matching works by specificity:

- province match wins over region match
- region match wins over country match
- if two zones have the same specificity, the highest `shipping_zones.priority` wins
- if priority is also equal, the lowest id wins

The destination is resolved in this order:

- use `addresses.shipping.province_id` when present
- otherwise try `addresses.shipping.state` as a province code
- use `addresses.shipping.country_id` as the country-level fallback

### Practical examples

#### 1. Flat shipping

If the host app sets:

```php
'shipping' => [
    'strategy' => 'flat',
],
```

and creates a method like:

```json
{
  "code": "express",
  "name": "Express Courier",
  "active": true,
  "flat_fee": 9.90,
  "volumetric_divisor": 5000
}
```

then a cart created with that method:

```json
{
  "shipping_method_id": 1,
  "addresses": {
    "billing": { "country_id": 1 },
    "shipping": { "country_id": 1 }
  },
  "lines": [
    { "product_id": 10, "qty": 2 }
  ]
}
```

will use `shipping_fee = 9.90` regardless of zone matching.
Weights are still calculated and exposed on the cart response.

#### 2. Province overrides region and country

Suppose one courier is linked to three active zones:

- `Italy` zone with `country_ids` containing the Italy country id, priced at `7.00` up to `5kg`
- `Lazio` zone with `region_ids` containing the Lazio region id, priced at `9.00` up to `5kg`
- `Rome` zone with `province_ids` containing the Rome province id, priced at `12.00` up to `5kg`

For a shipping address in Rome province, Venditio picks the province zone and charges `12.00`.
For a shipping address in Viterbo province, Venditio falls back to the Lazio region zone and charges `9.00`.
For a shipping address in Milan province, Venditio falls back to the Italy country zone and charges `7.00`.

This is true even if all three zones are linked to the same method: the most specific destination always wins.

#### 3. Different couriers can produce different volumetric fees

Take the same parcel with:

- actual weight `4kg`
- dimensions `50 x 40 x 30 cm`

Courier A has `volumetric_divisor = 5000`.
Courier B has `volumetric_divisor = 4000`.

That produces:

- Courier A volumetric weight: `(50 * 40 * 30) / 5000 = 12kg`
- Courier B volumetric weight: `(50 * 40 * 30) / 4000 = 15kg`

So the chargeable weight becomes:

- Courier A: `max(4, 12) = 12kg`
- Courier B: `max(4, 15) = 15kg`

If both couriers are linked to the same zone but with different pricing in `shipping_method_zones`, the final fee can differ twice:

- because the chargeable weight is different
- because each method-zone pivot can have different `rate_tiers` or `over_weight_price_per_kg`

#### 4. Incomplete destination does not block the cart

If the cart has lines but is missing `shipping_method_id`, or the shipping address is still incomplete, Venditio does not fail the request.

## Returns

Venditio ships with an API-first returns domain that stays aligned with the package's headless approach:

- `return_reasons`: configurable database-backed reasons exposed through CRUD APIs
- `return_requests`: order-linked return headers that snapshot `orders.addresses.billing` at creation time
- partial quantities per `order_line`, so a line with `qty > 1` can be returned incrementally
- derived fields on `order_lines` for frontend/admin use: `requested_return_qty`, `returned_qty`, `has_return_requests`, `is_returned`, `is_fully_returned`

`return_requests` do not expose `return_request_lines` as a standalone CRUD resource in v1.
The nested `lines` payload is validated against the selected order, and quantities cannot exceed the remaining returnable amount for each order line.
It keeps:

- `shipping_fee = 0`
- `shipping_zone_id = null`

This is useful for checkout flows where the customer adds products before choosing a courier or completing the address.

#### 5. Complete destination with no valid shipping rate returns `422`

In `zones` mode, if the cart has:

- a valid `shipping_method_id`
- a complete enough destination to resolve a province or country

but the selected method is not linked to any matching active zone, Venditio returns a validation error.
The same happens if a matching zone exists but its pivot has no applicable `rate_tiers` and no `over_weight_price_per_kg`.

This makes the failure machine-readable for the host app while still allowing incomplete carts to remain valid during checkout.

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
