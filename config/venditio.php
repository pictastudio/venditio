<?php

use PictaStudio\Venditio\{Discounts, Dto, Enums, Generators, Models, Pricing};
use PictaStudio\Venditio\Pipelines\{Cart, CartLine, Order};
use PictaStudio\Venditio\Validations;

return [

    'activity_log' => [
        'enabled' => env('VENDITIO_ACTIVITY_LOG_ENABLED', false),
        'log_name' => env('VENDITIO_ACTIVITY_LOG_NAME', 'venditio'),
        'log_except' => env('VENDITIO_ACTIVITY_LOG_EXCEPT', ['updated_at']),
    ],

    'authorize_using_policies' => env('VENDITIO_AUTHORIZE_USING_POLICIES', true),

    /*
    |--------------------------------------------------------------------------
    | Commands
    |--------------------------------------------------------------------------
    |
    | Configure package console commands behavior.
    |
    */
    'commands' => [
        'release_stock_for_abandoned_carts' => [
            'enabled' => env('VENDITIO_RELEASE_STOCK_FOR_ABANDONED_CARTS_ENABLED', true),
            'inactive_for_minutes' => (int) env('VENDITIO_RELEASE_STOCK_FOR_ABANDONED_CARTS_INACTIVE_FOR_MINUTES', 1_440),
            'schedule_every_minutes' => (int) env('VENDITIO_RELEASE_STOCK_FOR_ABANDONED_CARTS_SCHEDULE_EVERY_MINUTES', 60),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | Specify the models to use
    |
    | Host applications can override any model class to extend behavior.
    |
    */
    'models' => [
        'address' => Models\Address::class,
        'brand' => Models\Brand::class,
        'cart' => Models\Cart::class,
        'cart_line' => Models\CartLine::class,
        'country' => Models\Country::class,
        'country_tax_class' => Models\CountryTaxClass::class,
        'currency' => Models\Currency::class,
        'discount' => Models\Discount::class,
        'discount_application' => Models\DiscountApplication::class,
        'inventory' => Models\Inventory::class,
        'municipality' => Models\Municipality::class,
        'order' => Models\Order::class,
        'order_line' => Models\OrderLine::class,
        'province' => Models\Province::class,
        'product' => Models\Product::class,
        'product_category' => Models\ProductCategory::class,
        'region' => Models\Region::class,
        'shipping_status' => Models\ShippingStatus::class,
        'tax_class' => Models\TaxClass::class,
        'user' => Models\User::class,
        'product_custom_field' => Models\ProductCustomField::class,
        'product_type' => Models\ProductType::class,
        'product_variant' => Models\ProductVariant::class,
        'product_variant_option' => Models\ProductVariantOption::class,
        'price_list' => Models\PriceList::class,
        'price_list_price' => Models\PriceListPrice::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    |
    | Map validation contract (interface) to implementation. The service provider
    | binds these into the container so Form Requests resolve the correct rules.
    | Remove an entry to disable validation for that resource; replace with a
    | custom class to override rules. Publish config to customize.
    |
    */
    'validations' => [
        Validations\Contracts\AddressValidationRules::class => Validations\AddressValidation::class,
        Validations\Contracts\BrandValidationRules::class => Validations\BrandValidation::class,
        Validations\Contracts\CartValidationRules::class => Validations\CartValidation::class,
        Validations\Contracts\CartLineValidationRules::class => Validations\CartLineValidation::class,
        Validations\Contracts\OrderValidationRules::class => Validations\OrderValidation::class,
        Validations\Contracts\ProductValidationRules::class => Validations\ProductValidation::class,
        Validations\Contracts\ProductCategoryValidationRules::class => Validations\ProductCategoryValidation::class,
        Validations\Contracts\ProductTypeValidationRules::class => Validations\ProductTypeValidation::class,
        Validations\Contracts\ProductVariantValidationRules::class => Validations\ProductVariantValidation::class,
        Validations\Contracts\ProductVariantOptionValidationRules::class => Validations\ProductVariantOptionValidation::class,
        Validations\Contracts\PriceListValidationRules::class => Validations\PriceListValidation::class,
        Validations\Contracts\PriceListPriceValidationRules::class => Validations\PriceListPriceValidation::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Address
    |--------------------------------------------------------------------------
    |
    */
    'addresses' => [
        'type_enum' => Enums\AddressType::class,

        'dto' => Dto\AddressDto::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cart
    |--------------------------------------------------------------------------
    |
    | Pipeline tasks are executed in the order they are defined
    |
    */
    'cart' => [
        'status_enum' => Enums\CartStatus::class,

        'dto' => Dto\CartDto::class,

        // pipelines
        'pipelines' => [
            'create' => [
                'pipes' => [
                    Cart\Pipes\FillDataFromPayload::class,
                    Cart\Pipes\GenerateIdentifier::class,
                    Cart\Pipes\CalculateLines::class,
                    Cart\Pipes\ReserveStock::class,
                    Cart\Pipes\CalculateShippingFees::class,
                    Cart\Pipes\ApplyDiscounts::class,
                    Cart\Pipes\CalculateTotals::class,
                ],
            ],
            'update' => [
                'pipes' => [
                    Cart\Pipes\FillDataFromPayload::class,
                    Cart\Pipes\UpdateLines::class,
                    Cart\Pipes\ReserveStock::class,
                    Cart\Pipes\CalculateShippingFees::class,
                    Cart\Pipes\ApplyDiscounts::class,
                    Cart\Pipes\CalculateTotals::class,
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cart Line
    |--------------------------------------------------------------------------
    |
    */
    'cart_line' => [
        'dto' => Dto\CartLineDto::class,

        // pipeline tasks are executed in the order they are defined
        'pipelines' => [
            'create' => [
                'pipes' => [
                    CartLine\Pipes\FillProductInformations::class,
                    CartLine\Pipes\ApplyDiscount::class,
                    CartLine\Pipes\CalculateTaxes::class,
                    CartLine\Pipes\CalculateTotal::class,
                ],
            ],
            'update' => [
                'pipes' => [
                    CartLine\Pipes\FillProductInformations::class,
                    CartLine\Pipes\ApplyDiscount::class,
                    CartLine\Pipes\CalculateTaxes::class,
                    CartLine\Pipes\CalculateTotal::class,
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Order
    |--------------------------------------------------------------------------
    |
    */
    'order' => [
        'status_enum' => Enums\OrderStatus::class,

        'dto' => Dto\OrderDto::class,

        // pipeline tasks are executed in the order they are defined
        'pipelines' => [
            'create' => [
                'pipes' => [
                    Order\Pipes\FillOrderFromCart::class,
                    Order\Pipes\GenerateIdentifier::class,
                    Order\Pipes\ApplyDiscounts::class,
                    Order\Pipes\CalculateLines::class,
                    Order\Pipes\RegisterDiscountUsages::class,
                    Order\Pipes\CommitStock::class,
                    Order\Pipes\ApproveOrder::class,
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Product
    |--------------------------------------------------------------------------
    |
    */
    'product' => [
        'status_enum' => Enums\ProductStatus::class,
        'measuring_unit_enum' => Enums\ProductMeasuringUnit::class,
        'sku_generator' => Generators\ProductSkuGenerator::class,
        'sku_prefix' => env('VENDITIO_PRODUCT_SKU_PREFIX', 'SW-'),
        'sku_counter_padding' => (int) env('VENDITIO_PRODUCT_SKU_COUNTER_PADDING', 0),
        'exclude_variants_from_index' => env('VENDITIO_PRODUCT_EXCLUDE_VARIANTS_FROM_INDEX', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Price Lists
    |--------------------------------------------------------------------------
    |
    | Enable multi-price support and optionally provide a custom resolver
    | that picks the right price for a product at runtime.
    |
    */
    'price_lists' => [
        'enabled' => env('VENDITIO_PRICE_LISTS_ENABLED', false),
        'resolver' => Pricing\DefaultProductPriceResolver::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Discounts
    |--------------------------------------------------------------------------
    |
    | Bindings and rule classes used to evaluate discount eligibility.
    | Host applications can override the calculator/resolver/usage recorder
    | implementations or completely replace the rules list.
    |
    */
    'discounts' => [
        'calculator' => Discounts\DiscountCalculator::class,
        'discountables_resolver' => Discounts\DiscountablesResolver::class,
        'usage_recorder' => Discounts\DiscountUsageRecorder::class,
        'rules' => [
            Discounts\Rules\LineScopeRule::class,
            Discounts\Rules\ActiveWindowRule::class,
            Discounts\Rules\MaxUsesRule::class,
            Discounts\Rules\MaxUsesPerUserRule::class,
            Discounts\Rules\MinimumOrderTotalRule::class,
            Discounts\Rules\OncePerCartRule::class,
        ],
        'cart_total' => [
            'calculator' => Discounts\CartTotalDiscountCalculator::class,
            // `subtotal` applies coupon to line totals (tax included),
            // `checkout_total` also includes shipping + payment fees.
            'base' => 'subtotal',
            'rules' => [
                Discounts\Rules\ActiveWindowRule::class,
                Discounts\Rules\MaxUsesRule::class,
                Discounts\Rules\MaxUsesPerUserRule::class,
                Discounts\Rules\OncePerCartRule::class,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Product Variants
    |--------------------------------------------------------------------------
    |
    */
    'product_variants' => [
        'name_separator' => ' / ',
        'name_suffix_separator' => ' - ',
        'copy_categories' => true,
        'copy_attributes_exclude' => [
            'id',
            'slug',
            'sku',
            'ean',
            'created_at',
            'updated_at',
            'deleted_at',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    |
    | Scopes configuration
    |
    */
    'scopes' => [
        'in_date_range' => [
            'allow_null' => true, // allow null values to pass when checking date range
            'include_start_date' => true, // include the start date in the date range
            'include_end_date' => true, // include the end date in the date range
        ],
        'routes_to_exclude' => [ // routes to exclude from applying the scopes
            // '*', // exclude all routes
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Api routes
    |--------------------------------------------------------------------------
    |
    | Api routes configuration
    |
    */
    'routes' => [
        'api' => [
            'v1' => [
                'prefix' => 'api/venditio/v1',
                'name' => 'api.venditio.v1',
                'middleware' => [
                    'api',
                    // 'auth:sanctum',
                ],
                'pagination' => [
                    'per_page' => 15,
                ],
            ],
            'enable' => true, // enable api routes
            'include_timestamps' => false, // include updated_at and deleted_at timestamps in api responses
            'json_resource_enable_wrapping' => false, // Illuminate\Http\Resources\Json\JsonResource::withoutWrapping();
        ],
    ],
];
