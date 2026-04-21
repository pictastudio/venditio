# Venditio API Reference

This document describes the HTTP APIs exposed by Venditio.

## Base URL and Versioning

Default base path:

- `/api/venditio/v1`

Configured via:

- `venditio.routes.api.enable`
- `venditio.routes.api.v1.prefix`
- `venditio.routes.api.v1.name`
- `venditio.routes.api.v1.middleware`

## Auth and Authorization

- No authentication middleware is enforced by default.
- Policy checks are optional and controlled by `venditio.authorize_using_policies`.
- When enabled, controllers authorize only if a matching gate/policy is registered by the host app.

## Response and Errors

- Responses are returned with Laravel API Resources.
- Resource wrapping is controlled by `venditio.routes.api.json_resource_enable_wrapping`.
- Timestamps in resources are controlled by `venditio.routes.api.include_timestamps`.
- Validation errors use standard Laravel `422` payloads.

## Common Query Parameters

Most index endpoints support:

- `all` boolean, returns full collection (no pagination)
- `id[]` array of ids
- `per_page` pagination size
- string field filters use case-insensitive partial matching (`LIKE %value%`)
- non-string field filters keep exact-match behavior

Additional supported filters:

- `user_id` on `/carts`
- `country_id` on `/regions`
- `region_id` on `/provinces`
- `province_id` on `/municipalities`
- `product_type_id` on `/product_variants`
- `product_variant_id` on `/product_variant_options`
- `product_id` and `price_list_id` on `/price_list_prices`
- `invoice_id`, `return_request_id`, and `identifier` on `/orders/{order}/credit_notes`
- `/return_reasons`: `code`, `name`, `description`, `is_active`
- `/return_requests`: `order_id`, `user_id`, `return_reason_id`, `is_accepted`, `is_verified`
- `as_tree` boolean on `/product_categories`
- `/products`: `include_variants` boolean, `exclude_variants` boolean, `brand_ids[]`, `category_ids[]`, `collection_ids[]`, `price`, `price_operator` (`>`, `<`, `>=`, `<=`, `=`)
  - supports `sort_by=price` with `sort_dir=asc|desc`
  - default behavior is controlled by `venditio.product.exclude_variants_from_index` (`true` by default)
  - when both are provided, `exclude_variants` takes precedence

Include parameters:

- `/products`: `include=brand,categories,collections,product_type,tax_class,variants,variants_options_table,price_breakdown` (and `price_lists` only when `venditio.price_lists.enabled=true`)
  - `price_breakdown` adds `price_calculated.price_source` and `price_calculated.discounts_applied`, so admin UIs can show which base price source was selected and the ordered automatic discounts applied to the product preview
- `/product_collections`: `include=products,discounts`
- `/tax_classes`: `include[]=countries`

Export-specific query parameters:

- `/exports/products`: `columns[]` (or `columns=id,name,sku`) and optional `filename`
- `/exports/orders`: `columns[]` (or `columns=order_id,line_id,...`) and optional `filename`
- both export endpoints support the same list filters used by `/products` and `/orders`

Invoice-specific notes:

- invoice routes are enabled by `venditio.invoices.enabled` (default: `false`)
- invoices are generated explicitly from an order and persisted as immutable snapshots
- each order supports one invoice document in v1
- the PDF endpoint renders from stored HTML, not from the current order state

Credit-note-specific notes:

- credit note routes are enabled by `venditio.credit_notes.enabled` (default: `false`)
- credit notes are generated explicitly from accepted return requests and persisted as immutable snapshots
- each accepted return request supports one credit note document in v1
- the PDF endpoint renders from stored HTML, not from the current order, invoice, or return request state

## Endpoints

### Products

- `GET /products`
- `GET /products/{product}`
- `POST /products`
- `PATCH /products/{product}`
- `DELETE /products/{product}`
- `GET /products/{product}/variants`
- `POST /products/{product}/variants`

### Product Categories

- `GET /product_categories`
- `GET /product_categories/{product_category}`
- `POST /product_categories`
- `PATCH /product_categories/{product_category}`
- `PATCH /product_categories/bulk/update`
- `DELETE /product_categories/{product_category}`

### Product Collections

- `GET /product_collections`
- `GET /product_collections/{product_collection}`
- `POST /product_collections`
- `PATCH /product_collections/{product_collection}`
- `DELETE /product_collections/{product_collection}`

### Product Types

- `GET /product_types`
- `GET /product_types/{product_type}`
- `POST /product_types`
- `PATCH /product_types/{product_type}`
- `DELETE /product_types/{product_type}`

### Product Variants (Axes)

- `GET /product_variants`
- `GET /product_variants/{product_variant}`
- `POST /product_variants`
- `PATCH /product_variants/{product_variant}`
- `DELETE /product_variants/{product_variant}`

### Product Variant Options (Values)

- `GET /product_variant_options`
- `GET /product_variant_options/{product_variant_option}`
- `POST /product_variant_options`
- `PATCH /product_variant_options/{product_variant_option}`
- `DELETE /product_variant_options/{product_variant_option}`

### Product Custom Fields

- `GET /product_custom_fields`
- `GET /product_custom_fields/{product_custom_field}`
- `POST /product_custom_fields`
- `PATCH /product_custom_fields/{product_custom_field}`
- `DELETE /product_custom_fields/{product_custom_field}`

### Brands

- `GET /brands`
- `GET /brands/{brand}`
- `POST /brands`
- `PATCH /brands/{brand}`
- `DELETE /brands/{brand}`

### Inventories

- `GET /inventories`
- `GET /inventories/{inventory}`
- `POST /inventories`
- `PATCH /inventories/{inventory}`
- `DELETE /inventories/{inventory}`

### Carts

- `GET /carts`
- `GET /carts/{cart}`
- `POST /carts`
- `PATCH /carts/{cart}`
- `DELETE /carts/{cart}`
- `POST /carts/{cart}/add_lines`
- `PATCH /carts/{cart}/update_lines`
- `POST /carts/{cart}/remove_lines`
- `POST /carts/{cart}/add_discount`

### Cart Lines

- `GET /cart_lines`
- `GET /cart_lines/{cart_line}`
- `POST /cart_lines`
- `PATCH /cart_lines/{cart_line}`
- `DELETE /cart_lines/{cart_line}`

### Orders

- `GET /orders`
- `GET /orders/{order}`
- `POST /orders`
- `PATCH /orders/{order}`
- `DELETE /orders/{order}`

### Invoices

- `POST /orders/{order}/invoice`
- `GET /orders/{order}/invoice`
- `GET /orders/{order}/invoice/pdf`

Returned invoice documents expose these stable fields:

- `id`
- `order_id`
- `identifier`
- `issued_at`
- `currency_code`
- `seller`
- `billing_address`
- `shipping_address`
- `lines`
- `totals`
- `payments`
- `template_key`
- `pdf_download_url`

Invoice generation returns `422` when:

- seller configuration is incomplete
- the order has no billing address
- the order has no lines
- order lines use mixed or missing currencies

### Credit Notes

- `GET /orders/{order}/credit_notes`
- `POST /orders/{order}/credit_notes`
- `GET /orders/{order}/credit_notes/{credit_note}`
- `GET /orders/{order}/credit_notes/{credit_note}/pdf`

`POST /orders/{order}/credit_notes` accepts:

- `return_request_id`

Returned credit note documents expose these stable fields:

- `id`
- `order_id`
- `invoice_id`
- `return_request_id`
- `identifier`
- `issued_at`
- `currency_code`
- `seller`
- `billing_address`
- `shipping_address`
- `references`
- `lines`
- `totals`
- `template_key`
- `pdf_download_url`

Credit note generation returns `422` when:

- the order does not have an invoice
- the return request belongs to a different order
- the return request is not accepted
- the return request has no active lines
- credited lines use mixed or missing currencies

### Exports

- `GET /exports/products`
- `GET /exports/orders`

Notes:

- exports are enabled by `venditio.exports.enabled` (default: `true`)
- product export columns are validated against `venditio.exports.products.allowed_columns`
- order export columns are validated against `venditio.exports.orders.allowed_columns`
- relation columns return readable values (for example `name`, `code`, `sku`) instead of numeric foreign keys when available
- order export flattens data with one row for each order line

### Order Lines

- `GET /order_lines`
- `GET /order_lines/{order_line}`
- `POST /order_lines`
- `PATCH /order_lines/{order_line}`
- `DELETE /order_lines/{order_line}`

Returned order lines expose these additive public fields:

- `requested_return_qty`
- `returned_qty`
- `has_return_requests`
- `is_returned`
- `is_fully_returned`

### Return Reasons

- `GET /return_reasons`
- `GET /return_reasons/{return_reason}`
- `POST /return_reasons`
- `PATCH /return_reasons/{return_reason}`
- `DELETE /return_reasons/{return_reason}`

### Return Requests

- `GET /return_requests`
- `GET /return_requests/{return_request}`
- `POST /return_requests`
- `PATCH /return_requests/{return_request}`
- `DELETE /return_requests/{return_request}`

Notes:

- `return_requests` snapshot `orders.addresses.billing` into `billing_address` when created
- `return_request_lines` are nested inside the `lines` payload and resource, not exposed as a standalone CRUD endpoint in v1
- each request supports a single `return_reason_id`, while multiple order lines can be included
- requested quantities are validated against the remaining non-deleted return quantity for each order line
- credited return requests become immutable through the API and can no longer be updated or deleted

### Discounts

- `GET /discounts`
- `GET /discounts/{discount}`
- `POST /discounts`
- `PATCH /discounts/{discount}`
- `DELETE /discounts/{discount}`

Discount columns are first-level fields on `discounts`:

- `type`, `value`, `code`, `name`, `active`, `starts_at`, `ends_at`
- `uses`, `max_uses`, `max_uses_per_user`, `one_per_user`
- `apply_to_cart_total`, `apply_once_per_cart`, `minimum_order_total`, `free_shipping`, `first_purchase_only`
- `discountable_type`, `discountable_id`

`discountable_type` accepts package morph aliases such as `product`, `product_category`, `product_collection`, `product_type`, `brand`, and `user`.

Discount list filters:

- `is_general` boolean, filters discounts without a discountable target (`discountable_type` and `discountable_id` are both `null`)
- `discountable_type` uses exact matching against the stored morph alias/class instead of partial string matching

### Discount Applications

- `GET /discount_applications`
- `GET /discount_applications/{discount_application}`
- `POST /discount_applications`
- `PATCH /discount_applications/{discount_application}`
- `DELETE /discount_applications/{discount_application}`

### Addresses

- `GET /addresses`
- `GET /addresses/{address}`
- `POST /addresses`
- `PATCH /addresses/{address}`
- `DELETE /addresses/{address}`

### Countries, Regions, Provinces, Municipalities (Read-only)

- `GET /countries`
- `GET /countries/{country}`
- `GET /regions`
- `GET /regions/{region}`
- `GET /provinces`
- `GET /provinces/{province}`
- `GET /municipalities`
- `GET /municipalities/{municipality}`

### Taxing and Shipping Metadata

- `GET /country_tax_classes`
- `GET /country_tax_classes/{country_tax_class}`
- `POST /country_tax_classes`
- `POST /country_tax_classes/bulk/upsert`
- `PATCH /country_tax_classes/{country_tax_class}`
- `DELETE /country_tax_classes/{country_tax_class}`
- `GET /tax_classes`
- `GET /tax_classes/{tax_class}`
- `POST /tax_classes`
- `PATCH /tax_classes/{tax_class}`
- `DELETE /tax_classes/{tax_class}`
- `GET /shipping_statuses`
- `GET /shipping_statuses/{shipping_status}`
- `POST /shipping_statuses`
- `PATCH /shipping_statuses/{shipping_status}`
- `DELETE /shipping_statuses/{shipping_status}`

### Currencies

- `GET /currencies`
- `GET /currencies/{currency}`
- `POST /currencies`
- `PATCH /currencies/{currency}`
- `DELETE /currencies/{currency}`

### Price Lists (Feature-flagged)

- `GET /price_lists`
- `GET /price_lists/{price_list}`
- `POST /price_lists`
- `PATCH /price_lists/{price_list}`
- `DELETE /price_lists/{price_list}`
- `GET /price_list_prices`
- `GET /price_list_prices/{price_list_price}`
- `POST /price_list_prices`
- `POST /price_list_prices/bulk/upsert`
- `PATCH /price_list_prices/{price_list_price}`
- `DELETE /price_list_prices/{price_list_price}`

If `venditio.price_lists.enabled=false`, these endpoints return `404`.

## Variant Workflow Example

1. Create product type.
2. Create variant axes for the type.
3. Create options for each axis.
4. Create a base product with `product_type_id`.
5. Generate variant products via `POST /api/venditio/v1/products/{product}/variants`.

## Configuration Highlights

See `config/venditio.php` for:

- route prefix/version/middleware
- model overrides
- validations binding map
- policy enable toggle
- price list feature flag and resolver
- discount rule pipelines
