<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use PictaStudio\Venditio\Enums\DiscountType;
use PictaStudio\Venditio\Enums\{OrderStatus, ProductStatus};
use PictaStudio\Venditio\Exports\V1\{OrdersByLineExport, ProductsExport};
use PictaStudio\Venditio\Models\{Brand, Currency, Order, Product, ProductCategory, ProductCollection, ShippingStatus, TaxClass};

use function Pest\Laravel\{get, getJson};

uses(RefreshDatabase::class);

it('exports products in excel with selected columns', function () {
    Excel::fake();

    $taxClass = TaxClass::factory()->create();
    $currency = Currency::factory()->create([
        'code' => 'USD',
        'name' => 'US Dollar',
    ]);

    $product = Product::factory()->create([
        'tax_class_id' => $taxClass->getKey(),
        'name' => 'Export Product',
        'sku' => 'EXPORT-PRD-001',
        'status' => ProductStatus::Published,
        'active' => true,
        'visible_from' => now()->subDay(),
        'visible_until' => now()->addDay(),
    ]);

    $product->inventory()->updateOrCreate([], [
        'stock' => 12,
        'stock_reserved' => 2,
        'stock_available' => 10,
        'stock_min' => 1,
        'minimum_reorder_quantity' => 8,
        'reorder_lead_days' => 21,
        'price' => 99.99,
        'price_includes_tax' => false,
        'currency_id' => $currency->getKey(),
    ]);

    get(config('venditio.routes.api.v1.prefix') . '/exports/products?columns=id,name,brand_id,currency_id,stock_available,minimum_reorder_quantity,reorder_lead_days&filename=products-selection')
        ->assertOk();

    Excel::assertDownloaded('products-selection.xlsx', function (ProductsExport $export) use ($product): bool {
        expect($export->headings())->toBe(['id', 'name', 'brand_id', 'currency_id', 'stock_available', 'minimum_reorder_quantity', 'reorder_lead_days']);

        $rows = $export->collection()->map(fn ($row): array => $export->map($row));

        expect($rows)->toHaveCount(1)
            ->and((int) $rows->first()[0])->toBe($product->getKey())
            ->and($rows->first()[1])->toBe('Export Product')
            ->and($rows->first()[2])->toBe((string) $product->brand?->name)
            ->and($rows->first()[3])->toBe('USD')
            ->and((int) $rows->first()[4])->toBe(10)
            ->and((int) $rows->first()[5])->toBe(8)
            ->and((int) $rows->first()[6])->toBe(21);

        return true;
    });
});

it('validates requested columns on product excel export', function () {
    getJson(config('venditio.routes.api.v1.prefix') . '/exports/products?columns=id,not_allowed_column')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['columns.1']);
});

it('filters product excel export by ids', function () {
    Excel::fake();

    $productA = Product::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $productB = Product::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $productC = Product::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    get(
        config('venditio.routes.api.v1.prefix')
        . '/exports/products?columns=id'
        . '&ids[]=' . $productA->getKey()
        . '&ids[]=' . $productC->getKey()
        . '&filename=products-by-ids'
    )->assertOk();

    Excel::assertDownloaded('products-by-ids.xlsx', function (ProductsExport $export) use ($productA, $productB, $productC): bool {
        $ids = $export->collection()
            ->pluck('id')
            ->all();

        expect($ids)->toEqualCanonicalizing([$productA->getKey(), $productC->getKey()])
            ->not->toContain($productB->getKey());

        return true;
    });
});

it('filters product excel export by multiple brands and categories', function () {
    Excel::fake();

    $brandA = Brand::factory()->create();
    $brandB = Brand::factory()->create();
    $brandC = Brand::factory()->create();

    $categoryA = ProductCategory::factory()->create();
    $categoryB = ProductCategory::factory()->create();
    $categoryC = ProductCategory::factory()->create();

    $matchingA = Product::factory()->create([
        'brand_id' => $brandA->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $matchingA->categories()->sync([$categoryA->getKey()]);

    $matchingB = Product::factory()->create([
        'brand_id' => $brandB->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $matchingB->categories()->sync([$categoryB->getKey()]);

    $notMatchingBrand = Product::factory()->create([
        'brand_id' => $brandC->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $notMatchingBrand->categories()->sync([$categoryA->getKey()]);

    $notMatchingCategory = Product::factory()->create([
        'brand_id' => $brandA->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $notMatchingCategory->categories()->sync([$categoryC->getKey()]);

    get(
        config('venditio.routes.api.v1.prefix')
        . '/exports/products?columns=id,sku'
        . '&brand_ids[]=' . $brandA->getKey()
        . '&brand_ids[]=' . $brandB->getKey()
        . '&category_ids[]=' . $categoryA->getKey()
        . '&category_ids[]=' . $categoryB->getKey()
        . '&filename=products-filtered'
    )->assertOk();

    Excel::assertDownloaded('products-filtered.xlsx', function (ProductsExport $export) use ($matchingA, $matchingB, $notMatchingBrand, $notMatchingCategory): bool {
        $ids = $export->collection()
            ->pluck('id')
            ->all();

        expect($ids)
            ->toContain($matchingA->getKey(), $matchingB->getKey())
            ->not->toContain($notMatchingBrand->getKey(), $notMatchingCategory->getKey());

        return true;
    });
});

it('exports and filters products by collections', function () {
    Excel::fake();

    $collectionA = ProductCollection::factory()->create(['name' => 'Spring Picks']);
    $collectionB = ProductCollection::factory()->create(['name' => 'Winter Picks']);

    $matching = Product::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $matching->collections()->sync([$collectionA->getKey()]);

    $notMatching = Product::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $notMatching->collections()->sync([$collectionB->getKey()]);

    get(
        config('venditio.routes.api.v1.prefix')
        . '/exports/products?columns=id,collection_ids'
        . '&collection_ids[]=' . $collectionA->getKey()
        . '&filename=products-by-collections'
    )->assertOk();

    Excel::assertDownloaded('products-by-collections.xlsx', function (ProductsExport $export) use ($matching, $notMatching): bool {
        expect($export->headings())->toBe(['id', 'collection_ids']);

        $rows = $export->collection()
            ->map(fn ($row): array => $export->map($row))
            ->values();

        expect($rows)->toHaveCount(1)
            ->and((int) $rows->first()[0])->toBe($matching->getKey())
            ->and((string) $rows->first()[1])->toContain('Spring Picks')
            ->and(collect($rows)->pluck(0)->map(fn (mixed $id): int => (int) $id)->all())
            ->not->toContain($notMatching->getKey());

        return true;
    });
});

it('filters and sorts product excel export by inventory price', function () {
    Excel::fake();

    $productLow = Product::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $productMid = Product::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $productHigh = Product::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $productLow->inventory()->update(['price' => 10]);
    $productMid->inventory()->update(['price' => 20]);
    $productHigh->inventory()->update(['price' => 30]);

    get(
        config('venditio.routes.api.v1.prefix')
        . '/exports/products?columns=id,sku'
        . '&price_operator=' . urlencode('>=')
        . '&price=20'
        . '&sort_by=price'
        . '&sort_dir=desc'
        . '&filename=products-by-price'
    )->assertOk();

    Excel::assertDownloaded('products-by-price.xlsx', function (ProductsExport $export) use ($productLow, $productMid, $productHigh): bool {
        $ids = $export->collection()
            ->pluck('id')
            ->values()
            ->all();

        expect($ids)->toBe([
            $productHigh->getKey(),
            $productMid->getKey(),
        ])->not->toContain($productLow->getKey());

        return true;
    });
});

it('exports orders with one row per order line', function () {
    Excel::fake();

    $taxClass = TaxClass::factory()->create();
    $currency = Currency::factory()->create();
    $shippingStatus = ShippingStatus::factory()->create([
        'external_code' => 'SHP-EXP-01',
        'name' => 'In Transit',
    ]);
    $discountModel = config('venditio.models.discount');
    $discount = $discountModel::query()->create([
        'discountable_type' => null,
        'discountable_id' => null,
        'type' => DiscountType::Percentage,
        'value' => 10,
        'name' => 'Export Discount',
        'code' => 'EXPORT10',
        'active' => true,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);

    $productA = Product::factory()->create([
        'tax_class_id' => $taxClass->getKey(),
        'name' => 'Line Product A',
        'sku' => 'LINE-A-001',
        'status' => ProductStatus::Published,
        'active' => true,
        'visible_from' => now()->subDay(),
        'visible_until' => now()->addDay(),
    ]);

    $productB = Product::factory()->create([
        'tax_class_id' => $taxClass->getKey(),
        'name' => 'Line Product B',
        'sku' => 'LINE-B-001',
        'status' => ProductStatus::Published,
        'active' => true,
        'visible_from' => now()->subDay(),
        'visible_until' => now()->addDay(),
    ]);

    $order = Order::factory()->create([
        'identifier' => 'ORDER-EXPORT-001',
        'status' => OrderStatus::Processing->value,
        'shipping_status_id' => $shippingStatus->getKey(),
    ]);

    $orderLineModel = config('venditio.models.order_line');

    $lineA = $orderLineModel::query()->create([
        'order_id' => $order->getKey(),
        'product_id' => $productA->getKey(),
        'currency_id' => $currency->getKey(),
        'product_name' => 'Line Product A',
        'product_sku' => 'LINE-A-001',
        'discount_id' => $discount->getKey(),
        'discount_code' => 'EXPORT10',
        'discount_amount' => 0,
        'unit_price' => 10,
        'purchase_price' => 5,
        'unit_discount' => 0,
        'unit_final_price' => 10,
        'unit_final_price_tax' => 2.2,
        'unit_final_price_taxable' => 7.8,
        'qty' => 2,
        'total_final_price' => 20,
        'tax_rate' => 22,
        'product_data' => ['sku' => 'STATIC-LINE-A', 'name' => 'Static Product A'],
    ]);

    $lineB = $orderLineModel::query()->create([
        'order_id' => $order->getKey(),
        'product_id' => $productB->getKey(),
        'currency_id' => $currency->getKey(),
        'product_name' => 'Line Product B',
        'product_sku' => 'LINE-B-001',
        'discount_id' => $discount->getKey(),
        'discount_code' => 'EXPORT10',
        'discount_amount' => 0,
        'unit_price' => 15,
        'purchase_price' => 8,
        'unit_discount' => 0,
        'unit_final_price' => 15,
        'unit_final_price_tax' => 3.3,
        'unit_final_price_taxable' => 11.7,
        'qty' => 3,
        'total_final_price' => 45,
        'tax_rate' => 22,
        'product_data' => ['sku' => 'STATIC-LINE-B', 'name' => 'Static Product B'],
    ]);

    $productA->update(['sku' => 'LIVE-CHANGED-A']);
    $productB->update(['sku' => 'LIVE-CHANGED-B']);

    get(config('venditio.routes.api.v1.prefix') . '/exports/orders?columns=order_identifier,order_status,order_user_id,order_shipping_status_id,order_tracking_code,order_tracking_link,order_last_tracked_at,order_courier_code,order_sub_total_taxable,order_sub_total_tax,order_sub_total,order_shipping_fee,order_payment_fee,order_discount_code,order_discount_amount,order_total_final,order_user_first_name,order_user_last_name,order_user_email,order_customer_notes,order_admin_notes,order_approved_at,line_product_id,line_currency_id,line_product_name,line_product_sku,line_discount_code,line_discount_amount,line_unit_price,line_purchase_price,line_unit_discount,line_unit_final_price,line_unit_final_price_tax,line_unit_final_price_taxable,line_qty,line_total_final_price,line_tax_rate&filename=orders-lines')
        ->assertOk();

    Excel::assertDownloaded('orders-lines.xlsx', function (OrdersByLineExport $export) use ($currency): bool {
        expect($export->headings())->toBe(['order_identifier', 'order_status', 'order_user_id', 'order_shipping_status_id', 'order_tracking_code', 'order_tracking_link', 'order_last_tracked_at', 'order_courier_code', 'order_sub_total_taxable', 'order_sub_total_tax', 'order_sub_total', 'order_shipping_fee', 'order_payment_fee', 'order_discount_code', 'order_discount_amount', 'order_total_final', 'order_user_first_name', 'order_user_last_name', 'order_user_email', 'order_customer_notes', 'order_admin_notes', 'order_approved_at', 'line_product_id', 'line_currency_id', 'line_product_name', 'line_product_sku', 'line_discount_code', 'line_discount_amount', 'line_unit_price', 'line_purchase_price', 'line_unit_discount', 'line_unit_final_price', 'line_unit_final_price_tax', 'line_unit_final_price_taxable', 'line_qty', 'line_total_final_price', 'line_tax_rate']);

        $rows = $export->collection()->map(fn ($row): array => $export->map($row));

        expect($rows)->toHaveCount(2)
            ->and($rows->every(fn (array $row): bool => $row[0] === 'ORDER-EXPORT-001'))->toBeTrue()
            ->and($rows->every(fn (array $row): bool => $row[1] === 'processing'))->toBeTrue()
            ->and($rows->every(fn (array $row): bool => $row[3] === 'SHP-EXP-01'))->toBeTrue()
            ->and($rows->every(fn (array $row): bool => in_array($row[22], ['STATIC-LINE-A', 'STATIC-LINE-B'], true)))->toBeTrue()
            ->and($rows->every(fn (array $row): bool => $row[23] === $currency->code))->toBeTrue()
            ->and($rows->every(fn (array $row): bool => in_array($row[24], ['Static Product A', 'Static Product B'], true)))->toBeTrue();

        return true;
    });
});
