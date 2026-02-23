<?php

namespace PictaStudio\Venditio\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use PictaStudio\Venditio\Models\{Country, Currency};

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $countries = collect(File::json(__DIR__ . '/data/countries.json') ?? []);

        $currencyIdsByCode = $countries
            ->pluck('currency_code')
            ->filter()
            ->map(fn (mixed $code): string => mb_strtoupper((string) $code))
            ->unique()
            ->sort()
            ->values()
            ->mapWithKeys(function (string $code): array {
                $currency = Currency::query()->firstOrCreate(
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

                return [$code => $currency->getKey()];
            });

        $countries = $countries
            ->map(function (array $country) use ($currencyIdsByCode): array {
                $currencyCode = mb_strtoupper((string) ($country['currency_code'] ?? ''));
                unset($country['currency_code']);

                return array_merge($country, [
                    'currency_id' => $currencyIdsByCode->get($currencyCode),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            })
            ->toArray();

        Country::unguard();

        Country::insert($countries);

        Country::reguard();
    }
}
