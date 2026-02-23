<?php

namespace PictaStudio\Venditio\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\{Artisan, Schema};
use Orchestra\Testbench\TestCase as Orchestra;
use PictaStudio\Translatable\TranslatableServiceProvider;
use PictaStudio\Venditio\Models\Currency;
use PictaStudio\Venditio\VenditioServiceProvider;
use ReflectionClass;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $translatableMigrationsPath = dirname(
            (new ReflectionClass(TranslatableServiceProvider::class))->getFileName(),
            2
        ) . '/database/migrations';

        if (is_dir($translatableMigrationsPath)) {
            $this->loadMigrationsFrom($translatableMigrationsPath);
        }

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'PictaStudio\\Venditio\\Database\\Factories\\' . class_basename($modelName) . 'Factory'
        );

        if (Schema::hasTable('currencies') && !Currency::query()->exists()) {
            Currency::query()->create([
                'name' => 'EUR',
                'code' => 'EUR',
                'symbol' => 'EUR',
                'exchange_rate' => 1,
                'decimal_places' => 2,
                'is_enabled' => true,
                'is_default' => true,
            ]);
        }
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        // $migration = include __DIR__.'/../database/migrations/create_venditio_table.php.stub';
        // $migration->up();

        // $migrations = collect(scandir(__DIR__ . '/../database/migrations'))
        //     ->reject(fn (string $file) => in_array($file, ['.', '..']))
        //     ->map(fn (string $file) => str($file)->beforeLast('.php'))
        //     ->toArray();

        // Artisan::call('migrate', ['--path' => 'database/migrations']);
    }

    protected function getPackageProviders($app)
    {
        return [
            TranslatableServiceProvider::class,
            VenditioServiceProvider::class,
        ];
    }
}
