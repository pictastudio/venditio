<?php

namespace PictaStudio\Venditio;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Application;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Route;
use PictaStudio\Venditio\Console\Commands\{InstallCommand, ReleaseStockForAbandonedCarts};
use PictaStudio\Venditio\Contracts\{CartIdentifierGeneratorInterface, CartTotalDiscountCalculatorInterface, DiscountCalculatorInterface, DiscountUsageRecorderInterface, DiscountablesResolverInterface, OrderIdentifierGeneratorInterface, ProductPriceResolverInterface, ProductSkuGeneratorInterface};
use PictaStudio\Venditio\Discounts\{CartTotalDiscountCalculator, DiscountCalculator, DiscountUsageRecorder, DiscountablesResolver};
use PictaStudio\Venditio\Dto\{CartDto, CartLineDto, OrderDto};
use PictaStudio\Venditio\Dto\Contracts\{CartDtoContract, CartLineDtoContract, OrderDtoContract};
use PictaStudio\Venditio\Facades\Venditio as VenditioFacade;
use PictaStudio\Venditio\Generators\{CartIdentifierGenerator, OrderIdentifierGenerator, ProductSkuGenerator};
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
            ->hasCommands([ReleaseStockForAbandonedCarts::class, InstallCommand::class])
            ->hasMigrations([
                'create_addresses_table',
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
                'create_brands_table',
                'create_product_categories_table',
                'create_discount_applications_table',
                'create_discounts_table',
                'create_products_table',
                'create_product_types_table',
                'create_product_category_product_table',
                'create_product_variants_table',
                'create_product_custom_fields_table',
                'create_product_variant_options_table',
                'create_product_configuration_table',
                'create_inventories_table',
                'create_price_lists_table',
                'create_price_list_prices_table',
                'create_carts_table',
                'create_cart_lines_table',
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

    public function packageBooted(): void
    {
        $this->registerPublishableAssets();
        $this->registerApiRoutes();
        $this->registerScheduledCommands();
        $this->bindValidationClasses();
        // $this->bindDtos();
        $this->registerFactoriesGuessing();
        $this->registerMorphMap();
        $this->bindDiscountClasses();
        $this->bindPricingClasses();
        $this->bindIdentifierGenerators();
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

    // private function bindDtos(): void
    // {
    //     $this->app->singleton(OrderDtoContract::class, fn (Application $app) => OrderDto::bindIntoContainer());
    //     $this->app->singleton(CartDtoContract::class, fn (Application $app) => CartDto::bindIntoContainer());
    //     $this->app->singleton(CartLineDtoContract::class, fn (Application $app) => CartLineDto::bindIntoContainer());
    // }

    private function registerApiRoutes(): void
    {
        if (!config('venditio.routes.api.enable')) {
            return;
        }

        if (!config('venditio.routes.api.json_resource_enable_wrapping')) {
            JsonResource::withoutWrapping();
        }

        $prefix = config('venditio.routes.api.v1.prefix');

        // Venditio::configureRateLimiting($prefix);
        // config('venditio.routes.api.v1.rate_limit.configure')();

        VenditioFacade::configureRateLimiting($prefix);

        Route::middleware(config('venditio.routes.api.v1.middleware'))
            ->prefix($prefix)
            ->name(mb_rtrim(config('venditio.routes.api.v1.name'), '.') . '.')
            ->group(fn () => (
                $this->loadRoutesFrom($this->package->basePath('/../routes/v1/api.php'))
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
