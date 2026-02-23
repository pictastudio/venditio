<?php

use Illuminate\Support\Facades\Route;
use PictaStudio\Venditio\Http\Controllers\Api\V1\{AddressController, BrandController, CartController, CartLineController, CountryController, CountryTaxClassController, CurrencyController, DiscountApplicationController, DiscountController, InventoryController, MunicipalityController, OrderController, OrderLineController, PriceListController, PriceListPriceController, ProductCategoryController, ProductController, ProductCustomFieldController, ProductTypeController, ProductVariantController, ProductVariantOptionController, ProvinceController, RegionController, ShippingCarrierController, ShippingRateController, ShippingRateTierController, ShippingStatusController, ShippingZoneController, ShippingZoneMemberController, TaxClassController};

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

Route::apiResource('product_categories', ProductCategoryController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
Route::apiResource('product_types', ProductTypeController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
Route::apiResource('product_variants', ProductVariantController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
Route::apiResource('product_variant_options', ProductVariantOptionController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
Route::apiResource('carts', CartController::class);
Route::post('carts/{cart}/add_lines', [CartController::class, 'addLines'])->name('carts.addLines');
Route::post('carts/{cart}/remove_lines', [CartController::class, 'removeLines'])->name('carts.removeLines');
Route::post('carts/{cart}/add_discount', [CartController::class, 'addDiscount'])->name('carts.addDiscount');
Route::patch('carts/{cart}/update_lines', [CartController::class, 'updateLines'])->name('carts.updateLines');
Route::get('carts/{cart}/shipping_quotes', [CartController::class, 'shippingQuotes'])->name('carts.shippingQuotes');
Route::post('carts/{cart}/shipping_selection', [CartController::class, 'selectShippingOption'])->name('carts.selectShippingOption');
Route::apiResource('orders', OrderController::class);
Route::apiResource('addresses', AddressController::class);
Route::apiResource('brands', BrandController::class);
Route::apiResource('inventories', InventoryController::class);
Route::apiResource('countries', CountryController::class)->only(['index', 'show']);
Route::apiResource('regions', RegionController::class)->only(['index', 'show']);
Route::apiResource('provinces', ProvinceController::class)->only(['index', 'show']);
Route::apiResource('municipalities', MunicipalityController::class)->only(['index', 'show']);
Route::apiResource('country_tax_classes', CountryTaxClassController::class);
Route::apiResource('currencies', CurrencyController::class);
Route::apiResource('tax_classes', TaxClassController::class);
Route::apiResource('shipping_statuses', ShippingStatusController::class);
Route::apiResource('shipping_carriers', ShippingCarrierController::class);
Route::apiResource('shipping_zones', ShippingZoneController::class);
Route::apiResource('shipping_zone_members', ShippingZoneMemberController::class)->only(['index', 'show', 'store', 'destroy']);
Route::apiResource('shipping_rates', ShippingRateController::class);
Route::apiResource('shipping_rate_tiers', ShippingRateTierController::class);
Route::apiResource('discounts', DiscountController::class);
Route::apiResource('discount_applications', DiscountApplicationController::class);
Route::apiResource('product_custom_fields', ProductCustomFieldController::class);
Route::apiResource('price_lists', PriceListController::class);
Route::apiResource('price_list_prices', PriceListPriceController::class);
Route::apiResource('cart_lines', CartLineController::class);
Route::apiResource('order_lines', OrderLineController::class);
