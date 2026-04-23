<?php

namespace PictaStudio\Venditio;

use Barryvdh\DomPDF\ServiceProvider as DomPdfServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Maatwebsite\Excel\ExcelServiceProvider;
use PictaStudio\Venditio\Console\Commands\{InstallCommand, ReleaseStockForAbandonedCarts, SeedRandomDataCommand};
use PictaStudio\Venditio\Contracts\{CartIdentifierGeneratorInterface, CartTotalDiscountCalculatorInterface, CreditNoteNumberGeneratorInterface, CreditNotePayloadFactoryInterface, CreditNotePdfRendererInterface, CreditNoteTemplateInterface, DiscountCalculatorInterface, DiscountUsageRecorderInterface, DiscountablesResolverInterface, InvoiceNumberGeneratorInterface, InvoicePayloadFactoryInterface, InvoicePdfRendererInterface, InvoiceSellerResolverInterface, InvoiceTemplateInterface, OrderIdentifierGeneratorInterface, ProductPriceResolverInterface, ProductSkuGeneratorInterface, ShippingFeeCalculatorInterface, ShippingWeightsResolverInterface, ShippingZoneResolverInterface};
use PictaStudio\Venditio\CreditNotes\DefaultCreditNotePayloadFactory;
use PictaStudio\Venditio\CreditNotes\Renderers\DompdfCreditNotePdfRenderer;
use PictaStudio\Venditio\CreditNotes\Templates\DefaultCreditNoteTemplate;
use PictaStudio\Venditio\Discounts\{CartTotalDiscountCalculator, DiscountCalculator, DiscountUsageRecorder, DiscountablesResolver};
use PictaStudio\Venditio\Facades\Venditio as VenditioFacade;
use PictaStudio\Venditio\Generators\{CartIdentifierGenerator, CreditNoteNumberGenerator, InvoiceNumberGenerator, OrderIdentifierGenerator, ProductSkuGenerator};
use PictaStudio\Venditio\Http\Middleware\ResolveVenditioRouteBindings;
use PictaStudio\Venditio\Invoices\{DefaultInvoicePayloadFactory, DefaultInvoiceSellerResolver};
use PictaStudio\Venditio\Invoices\Renderers\DompdfInvoicePdfRenderer;
use PictaStudio\Venditio\Invoices\Templates\DefaultInvoiceTemplate;
use PictaStudio\Venditio\Models\User;
use PictaStudio\Venditio\Pricing\DefaultProductPriceResolver;
use Spatie\LaravelPackageTools\{Package, PackageServiceProvider};

class VenditioServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('venditio')
            ->hasConfigFile()
            // ->hasInstallCommand(function(InstallCommand $command): void {
            //     $command
            //         ->publishConfigFile()
            //         ->publishAssets()
            //         ->publishMigrations()
            //         ->askToRunMigrations()
            //         ->copyAndRegisterServiceProviderInApp();
            // })
            ->hasCommands([ReleaseStockForAbandonedCarts::class, SeedRandomDataCommand::class, InstallCommand::class])
            ->hasMigrations([
                'create_addresses_table',
                'update_addresses_add_sdi_pec',
                'create_countries_table',
                'create_regions_table',
                'create_provinces_table',
                'create_municipalities_table',
                'create_country_tax_class_table',
                'create_tax_classes_table',
                'create_currencies_table',
                'create_orders_table',
                'create_order_lines_table',
                'create_shipping_statuses_table',
                'create_shipping_methods_table',
                'create_shipping_zones_table',
                'create_shipping_zone_country_table',
                'create_shipping_zone_region_table',
                'create_shipping_zone_province_table',
                'create_shipping_method_zone_table',
                'update_orders_add_shipping_fields',
                'create_brands_table',
                'update_brands_add_catalog_fields',
                'create_product_categories_table',
                'update_product_categories_add_catalog_fields',
                'create_product_collections_table',
                'create_discount_applications_table',
                'update_discount_applications_order_line_constraint',
                'create_discounts_table',
                'update_discounts_add_first_purchase_only',
                'create_products_table',
                'create_product_types_table',
                'create_tags_table',
                'update_tags_images_to_collection',
                'create_product_category_product_table',
                'create_product_collection_product_table',
                'create_taggables_table',
                'create_product_variants_table',
                'update_product_variants_add_accept_hex_color',
                'create_product_custom_fields_table',
                'create_product_variant_options_table',
                'update_product_variant_options_remove_image_column',
                'create_product_configuration_table',
                'create_inventories_table',
                'update_inventories_add_reorder_fields',
                'create_price_lists_table',
                'create_price_list_prices_table',
                'create_carts_table',
                'update_carts_add_shipping_fields',
                'create_cart_lines_table',
                'create_free_gifts_table',
                'create_free_gift_user_table',
                'create_free_gift_qualifying_product_table',
                'create_free_gift_product_table',
                'create_cart_free_gift_decisions_table',
                'update_cart_lines_add_free_gift_fields',
                'update_order_lines_add_free_gift_fields',
                'create_return_reasons_table',
                'create_return_requests_table',
                'create_return_request_lines_table',
                'create_invoices_table',
                'create_credit_notes_table',
                'update_order_lines_add_return_fields',
                'seed_venditio_data',
            ]);
        // ->hasRoute('api');
    }

    public function registeringPackage()
    {
        $this->app->bind('venditio', fn (Application $app) => (
            $app->make(Venditio::class)
        ));
    }

    public function packageRegistered(): void
    {
        $this->mergeVenditioConfig();
    }

    public function packageBooted(): void
    {
        $this->registerExcelProvider();
        $this->registerDomPdfProvider();
        $this->registerPublishableAssets();
        $this->registerApiRoutes();
        $this->registerScheduledCommands();
        $this->bindValidationClasses();
        $this->registerFactoriesGuessing();
        $this->registerMorphMap();
        $this->bindDiscountClasses();
        $this->bindPricingClasses();
        $this->bindShippingClasses();
        $this->bindIdentifierGenerators();
        $this->bindInvoiceClasses();
        $this->bindCreditNoteClasses();
    }

    private function registerExcelProvider(): void
    {
        if (!class_exists(ExcelServiceProvider::class)) {
            return;
        }

        if ($this->app->getProviders(ExcelServiceProvider::class)) {
            return;
        }

        $this->app->register(ExcelServiceProvider::class);
    }

    private function registerDomPdfProvider(): void
    {
        if (!class_exists(DomPdfServiceProvider::class)) {
            return;
        }

        if ($this->app->getProviders(DomPdfServiceProvider::class)) {
            return;
        }

        $this->app->register(DomPdfServiceProvider::class);
    }

    private function mergeVenditioConfig(): void
    {
        $packageConfig = require dirname(__DIR__) . '/config/venditio.php';
        $applicationConfig = config('venditio', []);

        config()->set(
            'venditio',
            $this->mergeConfigRecursively(
                $packageConfig,
                is_array($applicationConfig) ? $applicationConfig : []
            )
        );
    }

    /**
     * Merge associative config arrays recursively while preserving list overrides.
     *
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function mergeConfigRecursively(array $defaults, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if (
                array_key_exists($key, $defaults)
                && is_array($defaults[$key])
                && is_array($value)
                && !array_is_list($defaults[$key])
                && !array_is_list($value)
            ) {
                $defaults[$key] = $this->mergeConfigRecursively($defaults[$key], $value);

                continue;
            }

            $defaults[$key] = $value;
        }

        return $defaults;
    }

    private function bindIdentifierGenerators(): void
    {
        $this->app->singleton(CartIdentifierGeneratorInterface::class, CartIdentifierGenerator::class);
        $this->app->singleton(OrderIdentifierGeneratorInterface::class, OrderIdentifierGenerator::class);
        $this->app->singleton(
            ProductSkuGeneratorInterface::class,
            config('venditio.product.sku_generator', ProductSkuGenerator::class)
        );
    }

    private function bindInvoiceClasses(): void
    {
        $this->app->singleton(
            InvoiceSellerResolverInterface::class,
            fn (Application $app) => $app->make(
                config('venditio.invoices.seller_resolver', DefaultInvoiceSellerResolver::class)
            )
        );

        $this->app->singleton(
            InvoiceNumberGeneratorInterface::class,
            fn (Application $app) => $app->make(
                config('venditio.invoices.number_generator', InvoiceNumberGenerator::class)
            )
        );

        $this->app->singleton(
            InvoicePayloadFactoryInterface::class,
            fn (Application $app) => $app->make(
                config('venditio.invoices.payload_factory', DefaultInvoicePayloadFactory::class)
            )
        );

        $this->app->singleton(
            InvoiceTemplateInterface::class,
            fn (Application $app) => $app->make(
                config('venditio.invoices.template', DefaultInvoiceTemplate::class)
            )
        );

        $this->app->singleton(
            InvoicePdfRendererInterface::class,
            fn (Application $app) => $app->make(
                config('venditio.invoices.renderer', DompdfInvoicePdfRenderer::class)
            )
        );
    }

    private function bindCreditNoteClasses(): void
    {
        $this->app->singleton(
            CreditNoteNumberGeneratorInterface::class,
            fn (Application $app) => $app->make(
                config('venditio.credit_notes.number_generator', CreditNoteNumberGenerator::class)
            )
        );

        $this->app->singleton(
            CreditNotePayloadFactoryInterface::class,
            fn (Application $app) => $app->make(
                config('venditio.credit_notes.payload_factory', DefaultCreditNotePayloadFactory::class)
            )
        );

        $this->app->singleton(
            CreditNoteTemplateInterface::class,
            fn (Application $app) => $app->make(
                config('venditio.credit_notes.template', DefaultCreditNoteTemplate::class)
            )
        );

        $this->app->singleton(
            CreditNotePdfRendererInterface::class,
            fn (Application $app) => $app->make(
                config('venditio.credit_notes.renderer', DompdfCreditNotePdfRenderer::class)
            )
        );
    }

    private function bindValidationClasses(): void
    {
        $validations = config('venditio.validations', []);

        foreach ($validations as $contract => $implementation) {
            $this->app->singleton($contract, $implementation);
        }
    }

    private function bindPricingClasses(): void
    {
        $this->app->singleton(
            ProductPriceResolverInterface::class,
            config('venditio.price_lists.resolver', DefaultProductPriceResolver::class)
        );
    }

    private function bindShippingClasses(): void
    {
        $this->app->singleton(
            ShippingWeightsResolverInterface::class,
            config('venditio.shipping.weights_resolver')
        );
        $this->app->singleton(
            ShippingZoneResolverInterface::class,
            config('venditio.shipping.zone_resolver')
        );
        $this->app->singleton(
            ShippingFeeCalculatorInterface::class,
            config('venditio.shipping.fee_calculator')
        );
    }

    private function bindDiscountClasses(): void
    {
        $this->app->singleton(
            DiscountCalculatorInterface::class,
            config('venditio.discounts.calculator', DiscountCalculator::class)
        );

        $this->app->singleton(
            DiscountablesResolverInterface::class,
            config('venditio.discounts.discountables_resolver', DiscountablesResolver::class)
        );

        $this->app->singleton(
            DiscountUsageRecorderInterface::class,
            config('venditio.discounts.usage_recorder', DiscountUsageRecorder::class)
        );

        $this->app->singleton(
            CartTotalDiscountCalculatorInterface::class,
            config('venditio.discounts.cart_total.calculator', CartTotalDiscountCalculator::class)
        );
    }

    private function registerApiRoutes(): void
    {
        if (!config('venditio.routes.api.enable')) {
            return;
        }

        $prefix = config('venditio.routes.api.v1.prefix');
        $rateLimiter = config('venditio.routes.api.v1.rate_limiter');
        $middleware = config('venditio.routes.api.v1.middleware', []);

        if (filled($rateLimiter)) {
            VenditioFacade::configureRateLimiting(
                $rateLimiter,
                (int) config('venditio.routes.api.v1.rate_limit_per_minute', 600)
            );
        }

        if (
            filled($rateLimiter)
            && !collect($middleware)->contains(fn (mixed $entry) => $entry === 'throttle:' . $rateLimiter)
        ) {
            $middleware[] = 'throttle:' . $rateLimiter;
        }

        array_unshift($middleware, ResolveVenditioRouteBindings::class);

        $routesPath = base_path('routes/vendor/venditio/api.php');

        if (!is_file($routesPath)) {
            $routesPath = $this->package->basePath('/../routes/v1/api.php');
        }

        Route::middleware($middleware)
            ->prefix($prefix)
            ->name(mb_rtrim(config('venditio.routes.api.v1.name'), '.') . '.')
            ->group(fn () => (
                $this->loadRoutesFrom($routesPath)
            ));
    }

    private function registerFactoriesGuessing(): void
    {
        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => str($modelName)
                ->replace('Models', 'Database\\Factories')
                ->append('Factory')
                ->toString()
        );
    }

    private function registerMorphMap(): void
    {
        $morphMap = collect(config('venditio.models', []))
            ->filter(fn (mixed $model) => is_string($model) && class_exists($model))
            ->toArray();

        if (!isset($morphMap['user'])) {
            $morphMap['user'] = User::class;
        }

        Relation::morphMap($morphMap);
    }

    private function registerPublishableAssets(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            $this->package->basePath('/../bruno/venditio') => base_path('bruno/venditio'),
        ], 'venditio-bruno');

        $this->publishes([
            $this->package->basePath('/../routes/v1/api.php') => base_path('routes/vendor/venditio/api.php'),
        ], 'venditio-routes');

        $this->publishes([
            __DIR__ . '/../database/seeders/data/countries.json' => database_path('seeders/data/countries.json'),
            __DIR__ . '/../database/seeders/data/it/regions.json' => database_path('seeders/data/it/regions.json'),
            __DIR__ . '/../database/seeders/data/it/provinces.json' => database_path('seeders/data/it/provinces.json'),
            __DIR__ . '/../database/seeders/data/it/municipalities.json' => database_path('seeders/data/it/municipalities.json'),
        ], 'venditio-data');
    }

    private function registerScheduledCommands(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            if (!config('venditio.commands.release_stock_for_abandoned_carts.enabled', true)) {
                return;
            }

            $scheduleEveryMinutes = max(
                1,
                (int) config('venditio.commands.release_stock_for_abandoned_carts.schedule_every_minutes', 60)
            );

            $schedule
                ->command(ReleaseStockForAbandonedCarts::class)
                ->everyMinute()
                ->withoutOverlapping()
                ->when(static function () use ($scheduleEveryMinutes): bool {
                    $minutesSinceMidnight = now()->startOfDay()->diffInMinutes(now());

                    return $minutesSinceMidnight % $scheduleEveryMinutes === 0;
                });
        });
    }
}
