# Changelog

All notable changes to `venditio` will be documented in this file.

## v1.3.0 - 2026-03-10

### What's Changed

#### Features

- **Product media metadata** - Product images and files now support stable backend-generated ids, `mimetype`, `sort_order`, `active`, and shared-media flags; images also support `thumbnail`. Media updates can modify metadata without re-uploading the underlying file.
- **Product media lifecycle** - Product media uploads append to the existing collection instead of replacing it, and products now expose dedicated media deletion by unique id with configurable filesystem cleanup.
- **Variant option shared media** - Added `POST /product/{product}/{productVariantOption}/upload` to upload media once for a variant option and propagate it to all matching variant products, storing assets under `products/{product_id}/variant_options/{variant_option_id}/...`.
- **Shared media semantics** - Variant-option media is marked with `shared_from_variant_option` and returned after variant-specific media in product API responses, making color-level galleries reusable across size variants without duplicating uploads.

#### API & Tooling

- **Bruno** - Added Bruno requests for product media deletion and variant-option media upload, and updated the product update request documentation for the expanded media payload fields.
- **Configuration** - Added `VENDITIO_PRODUCT_MEDIA_DELETE_FILES_FROM_FILESYSTEM` to let host apps decide whether filesystem assets should be removed when product media is deleted.

#### Tests

- Extended feature coverage for product media append/update/delete flows, media ordering and active filtering, shared variant-option media propagation, and shared-file deletion safety.

**Full Changelog**: https://github.com/pictastudio/venditio-core/compare/v1.2.5...v1.3.0

## v1.2.5 - 2026-03-10

### What's Changed

#### Fixes

- **API filters and global scopes** - Explicit list filters now override matching implicit global scopes in the shared API filter pipeline. Product `status` filters correctly return draft products, and explicit `active` / date-window filters no longer get masked by default active or visibility scopes.

#### Tests

- Added regression coverage for explicit `status`, `active`, and date-range filters against scoped endpoints.

**Full Changelog**: https://github.com/pictastudio/venditio/compare/v1.2.4...v1.2.5

## v1.2.4 - 2026-03-09

### What's Changed

#### Features

- **Translatable dependency range** - Relaxed the `pictastudio/translatable` Composer constraint from `^0.1` to `^0` so the package can install against the broader set of compatible stable `0.x` releases.

**Full Changelog**: https://github.com/pictastudio/venditio/compare/v1.2.3...v1.2.4

## v1.2.3 - 2026-03-09

### What's Changed

#### Features

- **Recursive config merge** - The service provider now merges package and application `venditio` config recursively during registration, preserving nested defaults while allowing host apps to override only the keys they need.

#### API & Tooling

- **Bruno locale header** - Added the `Locale` header to Bruno requests across the catalog endpoints and introduced a default `locale` environment variable for localized API testing.

**Full Changelog**: https://github.com/pictastudio/venditio/compare/v1.2.2...v1.2.3

## v1.2.1 - 2026-03-05

### What's Changed

#### Features

- **Price list prices bulk upsert** - Added `POST /price_list_prices/bulk/upsert` to create or update multiple prices across multiple products and price lists in a single request, using the natural key `product_id + price_list_id`.
- **Default price normalization in bulk flows** - Bulk upsert now enforces a single `is_default=true` price per product after persistence, keeping pricing resolution deterministic.
- **Validation for bulk payloads** - Added dedicated bulk request validation for `price_list_prices` with checks for required nested fields, duplicate `(product_id, price_list_id)` tuples in the same payload, and conflicting multiple defaults per product.
- **Authorization behavior** - Bulk endpoint applies policy checks per target row: `update` for existing tuples and `create` when new tuples are introduced.
- **Soft-deleted tuple handling** - Bulk upsert restores soft-deleted `price_list_prices` rows when matching tuples are re-submitted.

#### API & Tooling

- **Routes** - Registered `price_list_prices.upsertMultiple` route.
- **Bruno** - Added request collection entry for bulk upsert (`bruno/venditio/price_list_prices/07_bulk_upsert.bru`).
- **Docs** - Updated API reference with the new bulk endpoint.

#### Tests

- Extended `PriceListApiTest` coverage for:
  - successful multi-product/multi-price-list bulk upsert
  - default flag normalization behavior
  - validation errors for duplicate tuples and multiple defaults per product

**Full Changelog**: https://github.com/pictastudio/venditio/compare/1.2.0...v1.2.1

## v1.1.5 - 2026-03-04

### What's Changed

#### Features

- **Product categories batch update** - New bulk update endpoint for product categories. Supports updating multiple categories in a single request; validation and HTTP resource layer (transformations trait) fixes included.
- **Product list: includes and inventory price** - Products list and export support filtering and sorting by inventory price. Products index supports `include` query param to load relations (e.g. variants, categories). Documented in `docs/API.md`; Bruno requests updated.
- **Product media** - Fix images and files uploads for products. Validation and `UpdateProduct` action updated; `ProductResource`, `CartLineResource`, and `OrderLineResource` expose media URLs correctly.
- **Seed random data** - New `venditio:seed-random-data` Artisan command to seed products, variants, and related data for development. Configurable via `config/venditio.php`.
- **Discounts** - Fix discount usage recording; correct handling of discount applications and order line constraints. Migration added for `discount_applications` order line constraint. Price calculation fixes for discounted carts and order lines.
- **Bruno** - Bruno requests updated across list, store, and update operations for addresses, brands, cart lines, carts, countries, country tax classes, currencies, discount applications, discounts, inventories, municipalities, order lines, orders, product categories, product custom fields, product types, product variant options, product variants, products, provinces, regions, shipping statuses, and tax classes. New Bruno collections for price lists and price list prices (CRUD + associate multiple prices for product).
- **Products & exports** - Product resource and controller updates; export controller improvements (inventory price filtering/sorting). Cart line pipeline fills product information correctly.

#### Fixes

- Discount usage recording and order-line constraint for discount applications.
- HTTP resource layer `CanTransformAttributes` trait transformations.
- Price calculation when discounts are applied.
- Product images and files upload handling.

#### Tests

- Extended coverage for product category bulk update, discount pipeline, discount API resource, exports API (incl. inventory price params), price list API, product API (includes, inventory price filtering/sorting), product media upload API, and seed random data command.

**Full Changelog**: https://github.com/pictastudio/venditio/compare/v1.1.4...v1.1.5

## v1.1.4 - 2026-03-03

### What's Changed

#### Features

- **Product categories batch update** - New bulk update endpoint for product categories. Supports updating multiple categories in a single request; validation and HTTP resource layer (transformations trait) fixes included.
- **Discounts** - Fix discount usage recording; correct handling of discount applications and order line constraints. Migration added for `discount_applications` order line constraint. Price calculation fixes for discounted carts and order lines.
- **Bruno** - Bruno requests updated across list, store, and update operations for addresses, brands, cart lines, carts, countries, country tax classes, currencies, discount applications, discounts, inventories, municipalities, order lines, orders, product categories, product custom fields, product types, product variant options, product variants, products, provinces, regions, shipping statuses, and tax classes. New Bruno collections for price lists and price list prices (CRUD + associate multiple prices for product).
- **Products** - Product resource and controller updates; export controller improvements. Cart line pipeline now fills product information correctly.

#### Fixes

- Discount usage recording and order-line constraint for discount applications.
- HTTP resource layer `CanTransformAttributes` trait transformations.
- Price calculation when discounts are applied.

#### Tests

- Extended coverage for product category bulk update, discount pipeline, discount API resource, exports API, price list API, and product API.

**Full Changelog**: https://github.com/pictastudio/venditio/compare/v1.1.3...v1.1.4

## v1.1.3 - 2026-03-03

### What's Changed

#### Features

- **Exports API** - New Excel export endpoints for orders (by line) and products. Configurable via `config/venditio.php`; supports filtering and format options. See `docs/API.md` for usage.
- **List query params** - List endpoints now support consistent query parameters for filtering, sorting, and pagination across addresses, brands, carts, cart lines, countries, currencies, discount applications, discounts, inventories, municipalities, order lines, orders, product categories, product custom fields, product types, product variant options, product variants, products, provinces, regions, shipping statuses, and tax classes.
- **Products index** - Option to exclude product variants from the products list response; additional query param filters for products.
- **Product variants** - Enforce unique product variant and product variant option combination; product variant options are now translatable.
- **Slugs** - Validation and slug handling fixes for brands, product categories, and product types.

#### API & tooling

- **Bruno** - Bruno requests updated with query params and documentation for all list endpoints.
- **Docs** - API documentation updated for exports and query parameters.

#### Maintenance

- Code cleanup and config simplification.
- Expanded test coverage for exports API, product API (filters, exclude variants), list query params, product variant definitions, and translatable catalog.

**Full Changelog**: https://github.com/pictastudio/venditio/compare/v1.1.2...v1.1.3

## v1.1.2 - 2026-02-26

**Full Changelog**: https://github.com/pictastudio/venditio/compare/v1.1.1...v1.1.2

## v1.1.1 - 2026-02-26

### What's Changed

- resolve route model binding by id or slug (multilingual)

**Full Changelog**: https://github.com/pictastudio/venditio/compare/v1.1.0...v1.1.1

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
