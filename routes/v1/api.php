<?php

use Illuminate\Support\Facades\Route;
use PictaStudio\Venditio\Http\Controllers\Api\V1\{AddressController, BrandController, CartController, CartLineController, CountryController, CountryTaxClassController, CreditNoteController, CurrencyController, DiscountApplicationController, DiscountController, ExportController, FreeGiftController, InventoryController, InvoiceController, MunicipalityController, OrderController, OrderLineController, PriceListController, PriceListPriceController, ProductCategoryController, ProductCollectionController, ProductController, ProductCustomFieldController, ProductTypeController, ProductVariantController, ProductVariantOptionController, ProvinceController, RegionController, ReturnReasonController, ReturnRequestController, ShippingMethodController, ShippingMethodZoneController, ShippingStatusController, ShippingZoneController, TagController, TaxClassController};

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::apiResource('products', ProductController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
Route::get('products/{product}/variants', [ProductController::class, 'variants'])->name('products.variants');
Route::post('products/{product}/variants', [ProductController::class, 'createVariants'])->name('products.createVariants');
Route::delete('products/{product}/media/{mediaId}', [ProductController::class, 'destroyMedia'])->name('products.media.destroy');
Route::post('product/{product}/{productVariantOption}/upload', [ProductController::class, 'uploadVariantOptionMedia'])->name('product.variantOptionMedia.upload');

Route::patch('product_categories/bulk/update', [ProductCategoryController::class, 'updateMultiple'])->name('product_categories.updateMultiple');
Route::apiResource('product_categories', ProductCategoryController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
Route::apiResource('product_collections', ProductCollectionController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
Route::apiResource('tags', TagController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
Route::apiResource('product_types', ProductTypeController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
Route::apiResource('product_variants', ProductVariantController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
Route::apiResource('product_variant_options', ProductVariantOptionController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
Route::apiResource('carts', CartController::class);
Route::post('carts/{cart}/add_lines', [CartController::class, 'addLines'])->name('carts.addLines');
Route::post('carts/{cart}/remove_lines', [CartController::class, 'removeLines'])->name('carts.removeLines');
Route::post('carts/{cart}/add_discount', [CartController::class, 'addDiscount'])->name('carts.addDiscount');
Route::patch('carts/{cart}/update_lines', [CartController::class, 'updateLines'])->name('carts.updateLines');
Route::patch('carts/{cart}/free_gifts', [CartController::class, 'updateFreeGifts'])->name('carts.updateFreeGifts');
Route::apiResource('orders', OrderController::class);
if (config('venditio.invoices.enabled', false)) {
    Route::post('orders/{order}/invoice', [InvoiceController::class, 'store'])->name('orders.invoice.store');
    Route::get('orders/{order}/invoice', [InvoiceController::class, 'show'])->name('orders.invoice.show');
    Route::get('orders/{order}/invoice/pdf', [InvoiceController::class, 'pdf'])->name('orders.invoice.pdf');
}
if (config('venditio.credit_notes.enabled', false)) {
    Route::get('orders/{order}/credit_notes', [CreditNoteController::class, 'index'])->name('orders.credit_notes.index');
    Route::post('orders/{order}/credit_notes', [CreditNoteController::class, 'store'])->name('orders.credit_notes.store');
    Route::get('orders/{order}/credit_notes/{credit_note}', [CreditNoteController::class, 'show'])->name('orders.credit_notes.show');
    Route::get('orders/{order}/credit_notes/{credit_note}/pdf', [CreditNoteController::class, 'pdf'])->name('orders.credit_notes.pdf');
}
if (config('venditio.exports.enabled', true)) {
    Route::get('exports/products', [ExportController::class, 'products'])->name('exports.products');
    Route::get('exports/orders', [ExportController::class, 'orders'])->name('exports.orders');
}
Route::apiResource('addresses', AddressController::class);
Route::apiResource('brands', BrandController::class);
Route::apiResource('inventories', InventoryController::class);
Route::apiResource('countries', CountryController::class)->only(['index', 'show']);
Route::apiResource('regions', RegionController::class)->only(['index', 'show']);
Route::apiResource('provinces', ProvinceController::class)->only(['index', 'show']);
Route::apiResource('municipalities', MunicipalityController::class)->only(['index', 'show']);
Route::post('country_tax_classes/bulk/upsert', [CountryTaxClassController::class, 'upsertMultiple'])->name('country_tax_classes.upsertMultiple');
Route::apiResource('country_tax_classes', CountryTaxClassController::class);
Route::apiResource('currencies', CurrencyController::class);
Route::apiResource('tax_classes', TaxClassController::class);
Route::apiResource('shipping_methods', ShippingMethodController::class);
Route::apiResource('shipping_method_zones', ShippingMethodZoneController::class);
Route::apiResource('shipping_statuses', ShippingStatusController::class);
Route::apiResource('shipping_zones', ShippingZoneController::class);
Route::apiResource('free_gifts', FreeGiftController::class);
Route::post('discounts/bulk/upsert', [DiscountController::class, 'upsertMultiple'])->name('discounts.upsertMultiple');
Route::apiResource('discounts', DiscountController::class);
Route::apiResource('discount_applications', DiscountApplicationController::class);
Route::apiResource('product_custom_fields', ProductCustomFieldController::class);
Route::apiResource('price_lists', PriceListController::class);
Route::post('price_list_prices/bulk/upsert', [PriceListPriceController::class, 'upsertMultiple'])->name('price_list_prices.upsertMultiple');
Route::apiResource('price_list_prices', PriceListPriceController::class);
Route::apiResource('return_reasons', ReturnReasonController::class);
Route::apiResource('return_requests', ReturnRequestController::class);
Route::apiResource('cart_lines', CartLineController::class);
Route::apiResource('order_lines', OrderLineController::class);
