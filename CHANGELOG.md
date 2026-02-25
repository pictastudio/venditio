# Changelog

All notable changes to `venditio` will be documented in this file.

## v1.1.0 - 2026-02-25

### What's Changed

#### Features

- **Currency support** - Add currency to inventories, cart lines, and order lines; seed currencies and simplify country-currency relationship (belongs-to).
- **Slugs** - Add slug to brands, product categories, and product types.
- **Geography** - Add regions, provinces, and municipalities.
- **Defaults & seeding** - Default product type and default tax class with automatic assignment when not provided; seed base data in a migration.
- **Translatable** - Integrate translatable library for translatable content.
- **VAT** - Resolve correct VAT rate for the address country.

#### API & HTTP

- **Dedicated HTTP resources** - Create dedicated HTTP resources for every model (no raw model exposure).
- **Auth & policies** - Remove opinionated auth checks and delegate authorization to policies (host app controls auth).

#### Validation & configuration

- **Validation rules** - Refactor all validation rules to array format instead of pipe-separated strings.
- **Validation classes** - Bind validation classes from config for overridability.
- **Soft deletes** - Add missing soft deletes where applicable.

#### Data & logic

- **SKU** - Updated SKU generation logic.
- **Countries & currencies** - Update countries and currencies data.

#### Tooling & DX

- **Install** - Custom install command for package setup.
- **Migrations** - Publish required translatable migrations before Venditio migrations.
- **Bruno** - Update Bruno API requests.

#### Documentation & maintenance

- Updated docs and database schema.
- CHANGELOG updates.
- Naming updates and code format/linting.
- General fixes.

#### Dependencies

- Bump `actions/checkout` from 4 to 6 (GitHub Actions).
- Bump `stefanzweifel/git-auto-commit-action` from 5 to 7 (GitHub Actions).

**Full Changelog**: https://github.com/pictastudio/venditio/compare/v1.0.0...v1.1.0

## v1.0.0 - 2026-02-12

### What's Changed

* Arch update by @Frameck in https://github.com/pictastudio/venditio/pull/18
* multiple price lists by @Frameck in https://github.com/pictastudio/venditio/pull/19
* rename from `venditio-core` to `venditio` by @Frameck in https://github.com/pictastudio/venditio/pull/20

**Full Changelog**: https://github.com/pictastudio/venditio/compare/v0.1.8...v1.0.0

## v0.1.9 - 2024-11-18

### What's Changed

* Fix rate limiting configuration
* Bump aglipanci/laravel-pint-action from 2.3.1 to 2.4 by @dependabot in https://github.com/pictastudio/venditio/pull/3

**Full Changelog**: https://github.com/pictastudio/venditio/compare/v0.1.5...v0.1.9

## v0.1.8 - 2024-04-05

fix `FillOrderFromCart` and improved `ProductItemResource` api resource

## v0.1.7 - 2024-04-04

- [fix registering model policies](https://github.com/pictastudio/venditio/commit/84a8d61e88b8f4c81f4102f73dcb7c13690eb656)
- [test for registering model policies](https://github.com/pictastudio/venditio/commit/32418b5593f0fd6a014ccb125a3752685390bc34)

## v0.1.6 - 2024-03-28

fix resolve models from container

## v0.1.5 - 2024-03-28

fix resolve models from container

## v0.1.4 - 2024-03-28

allow null user in `AuthManager` constructor

## v0.1.3 - 2024-03-27

fix + rename `HasDataToValidate` to `ValidatesData`

## v0.1.2 - 2024-03-19

fix various + cart line pipeline

## v0.1.0 - 2024-03-12

v0.1.0

- setup models and migrations
- init api routes
- cart and order pipelines
