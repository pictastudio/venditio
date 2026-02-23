<?php

namespace PictaStudio\Venditio\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use PictaStudio\Venditio\Models\Currency;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        collect(File::json(__DIR__ . '/data/countries.json') ?? [])
            ->pluck('currency_code')
            ->filter()
            ->map(fn (mixed $code): string => mb_strtoupper((string) $code))
            ->unique()
            ->sort()
            ->values()
            ->each(function (string $code): void {
                Currency::query()->firstOrCreate(
                    ['code' => $code],
                    [
                        'name' => $code,
                        'symbol' => null,
                        'exchange_rate' => 1,
                        'decimal_places' => 2,
                        'is_enabled' => true,
                        'is_default' => false,
                    ]
                );
            });
    }
}
