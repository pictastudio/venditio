# Venditio Architecture

Venditio is a Laravel package that exposes API-only ecommerce capabilities.
It is headless by design: host applications own authentication, UI, and rendering.

## Goals

- API-first ecommerce primitives
- Stable public API contracts
- Extensibility via config, contracts, and model overrides
- Optional behavior with safe defaults

## Package Layout

- `src/Contracts`: public contracts/interfaces
- `src/Http`: API controllers, requests, resources
- `src/Models`: domain models
- `src/Actions`: reusable state-changing operations
- `src/Pipelines`: cart, cart-line, and order orchestration pipelines
- `src/Discounts`: discount calculators, context, rules, usage recording
- `src/Shipping`: shipping calculators and zone matcher implementations
- `src/Validations`: validation contract implementations
- `config/venditio.php`: package behavior configuration
- `database/migrations`: installable schema source of truth
- `database.dbml`: schema documentation snapshot aligned to migrations

## Service Provider

`VenditioServiceProvider` is the package entrypoint.

It configures:

- package config loading
- package migrations registration
- API route registration (when enabled)
- validation contract bindings from config
- discount/pricing/identifier-generator bindings
- polymorphic morph map
- scheduled command registration

Routes are loaded only when `venditio.routes.api.enable` is `true`.

## HTTP Layer

Controllers are thin and delegate to actions/pipelines.

- Form Requests validate payloads
- API Resources define response shape
- `Controller::applyBaseFilters()` provides base filtering + pagination
- `authorizeIfConfigured()` applies optional policy-based authorization

## Domain Model Notes

- Models are replaceable through `venditio.models`
- Product variants are modeled with `products.parent_id`
- Variant axes/values are represented by `product_variants` and `product_variant_options`
- Variant option assignments are represented by `product_configuration`

## Discounts

Discount behavior is configurable and rule-based.

Core components:

- `DiscountCalculator`: line-level discount application
- `CartTotalDiscountCalculator`: cart/order-level discount calculation
- `DiscountUsageRecorder`: persists usage in `discount_applications`

Rules are configured in:

- `venditio.discounts.rules`
- `venditio.discounts.cart_total.rules`

Host apps can swap rule classes by implementing `DiscountRuleInterface`.

## Validation

Validation is decoupled through contracts.

- Contracts in `src/Validations/Contracts`
- Implementations in `src/Validations`
- Bindings driven by `venditio.validations`

Host apps can override any validation class by changing the contract mapping.

## Authorization

Authorization is optional and host-controlled.

- Package does not enforce auth middleware by default
- Controllers perform policy checks only when configured and resolvable
- Host apps register policies/gates

## Configuration Surface

Main configuration sections:

- `routes.api`
- `models`
- `validations`
- `authorize_using_policies`
- `product`
- `product_variants`
- `price_lists`
- `discounts`
- `shipping`
- `commands`
- `scopes`

## Testing Strategy

Tests use Orchestra Testbench + Pest, focusing on:

- service provider boot and wiring
- route registration behavior
- API response shape and validation
- variant generation flows
- discount behaviors
- optional authorization behavior
