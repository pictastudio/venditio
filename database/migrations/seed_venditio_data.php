<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\File;
use PictaStudio\Venditio\Models\{Country, Currency, Municipality, ProductType, Province, Region, TaxClass};

return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $this->seedCountries();
        $this->seedRegions();
        $this->seedProvinces();
        $this->seedMunicipalities();
        $this->seedTaxClasses();
        $this->seedProductTypes();
    }

    private function seedCountries(): void
    {
        $countries = collect(File::json(__DIR__ . '/../seeders/data/countries.json') ?? []);

        $currencyIdsByCode = $countries
            ->pluck('currency_code')
            ->filter()
            ->map(fn (mixed $code): string => mb_strtoupper((string) $code))
            ->unique()
            ->sort()
            ->values()
            ->mapWithKeys(function (string $code): array {
                $createData = [
                    'name' => $code,
                    'symbol' => null,
                    'exchange_rate' => 1,
                    'decimal_places' => 2,
                    'is_enabled' => true,
                    'is_default' => false,
                ];

                if ($code === 'EUR') {
                    $createData['is_default'] = true;
                }

                $currency = Currency::query()->firstOrCreate(['code' => $code], $createData);

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

    private function seedRegions(): void
    {
        $regionFiles = File::glob(__DIR__ . '/../seeders/data/*/regions.json');

        if (empty($regionFiles)) {
            return;
        }

        $countryIdsByIso2 = Country::query()
            ->get(['id', 'iso_2'])
            ->mapWithKeys(fn (Country $country): array => [
                mb_strtolower((string) $country->iso_2) => $country->getKey(),
            ]);

        $regions = collect($regionFiles)
            ->flatMap(function (string $regionFile) use ($countryIdsByIso2) {
                $folderCountryCode = mb_strtolower(basename(dirname($regionFile)));
                $rows = File::json($regionFile) ?? [];

                return collect($rows)->map(function (array $region) use ($countryIdsByIso2, $folderCountryCode): array {
                    $countryCode = mb_strtolower((string) ($region['country_code'] ?? $folderCountryCode));

                    return [
                        'country_id' => $countryIdsByIso2->get($countryCode),
                        'name' => $region['name'] ?? null,
                        'code' => $region['code'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                });
            })
            ->filter(fn (array $region): bool => filled($region['country_id']) && filled($region['name']));

        if ($regions->isEmpty()) {
            return;
        }

        Region::unguard();

        $regions
            ->chunk(500)
            ->each(fn ($chunk): bool => Region::insert($chunk->values()->all()));

        Region::reguard();
    }

    private function seedProvinces(): void
    {
        $provinceFiles = File::glob(__DIR__ . '/../seeders/data/*/provinces.json');

        if (empty($provinceFiles)) {
            return;
        }

        $regionIdsByCountryAndCode = Region::query()
            ->with('country:id,iso_2')
            ->get(['id', 'country_id', 'code', 'name'])
            ->mapWithKeys(function (Region $region): array {
                $countryCode = mb_strtolower((string) $region->country?->iso_2);
                $regionCode = mb_strtolower((string) ($region->code ?? ''));
                $regionName = mb_strtolower((string) $region->name);

                return [
                    "{$countryCode}|{$regionCode}" => $region->getKey(),
                    "{$countryCode}|name:{$regionName}" => $region->getKey(),
                ];
            });

        $provinces = collect($provinceFiles)
            ->flatMap(function (string $provinceFile) use ($regionIdsByCountryAndCode) {
                $folderCountryCode = mb_strtolower(basename(dirname($provinceFile)));
                $rows = File::json($provinceFile) ?? [];

                return collect($rows)->map(function (array $province) use ($regionIdsByCountryAndCode, $folderCountryCode): array {
                    $countryCode = mb_strtolower((string) ($province['country_code'] ?? $folderCountryCode));
                    $regionCode = mb_strtolower((string) ($province['region_code'] ?? ''));
                    $regionName = mb_strtolower((string) ($province['region_name'] ?? ''));
                    $regionId = $regionIdsByCountryAndCode->get("{$countryCode}|{$regionCode}")
                        ?? $regionIdsByCountryAndCode->get("{$countryCode}|name:{$regionName}");

                    return [
                        'region_id' => $regionId,
                        'name' => $province['name'] ?? null,
                        'code' => $province['code'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                });
            })
            ->filter(fn (array $province): bool => filled($province['region_id']) && filled($province['name']));

        if ($provinces->isEmpty()) {
            return;
        }

        Province::unguard();

        $provinces
            ->chunk(500)
            ->each(fn ($chunk): bool => Province::insert($chunk->values()->all()));

        Province::reguard();
    }

    private function seedMunicipalities(): void
    {
        $municipalityFiles = File::glob(__DIR__ . '/../seeders/data/*/municipalities.json');

        if (empty($municipalityFiles)) {
            return;
        }

        $provinceIdsByCountryAndCode = Province::query()
            ->with('region.country:id,iso_2')
            ->get(['id', 'region_id', 'code', 'name'])
            ->mapWithKeys(function (Province $province): array {
                $countryCode = mb_strtolower((string) $province->region?->country?->iso_2);
                $provinceCode = mb_strtolower((string) ($province->code ?? ''));
                $provinceName = mb_strtolower((string) $province->name);

                return [
                    "{$countryCode}|{$provinceCode}" => $province->getKey(),
                    "{$countryCode}|name:{$provinceName}" => $province->getKey(),
                ];
            });

        $municipalities = collect($municipalityFiles)
            ->flatMap(function (string $municipalityFile) use ($provinceIdsByCountryAndCode) {
                $folderCountryCode = mb_strtolower(basename(dirname($municipalityFile)));
                $rows = File::json($municipalityFile) ?? [];

                return collect($rows)->map(function (array $municipality) use ($provinceIdsByCountryAndCode, $folderCountryCode): array {
                    $countryCode = mb_strtolower((string) ($municipality['country_code'] ?? $folderCountryCode));
                    $provinceCode = mb_strtolower((string) ($municipality['province_code'] ?? ''));
                    $provinceName = mb_strtolower((string) ($municipality['province'] ?? ''));
                    $provinceId = $provinceIdsByCountryAndCode->get("{$countryCode}|{$provinceCode}")
                        ?? $provinceIdsByCountryAndCode->get("{$countryCode}|name:{$provinceName}");

                    return [
                        'province_id' => $provinceId,
                        'name' => $municipality['name'] ?? null,
                        'country_zone' => $municipality['country_zone'] ?? null,
                        'zip' => $municipality['zip'] ?? null,
                        'phone_prefix' => $municipality['phone_prefix'] ?? null,
                        'istat_code' => $municipality['istat_code'] ?? null,
                        'cadastral_code' => $municipality['cadastral_code'] ?? null,
                        'latitude' => $municipality['latitude'] ?? null,
                        'longitude' => $municipality['longitude'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                });
            })
            ->filter(fn (array $municipality): bool => filled($municipality['province_id']) && filled($municipality['name']));

        if ($municipalities->isEmpty()) {
            return;
        }

        Municipality::unguard();

        $municipalities
            ->chunk(500)
            ->each(fn ($chunk): bool => Municipality::insert($chunk->values()->all()));

        Municipality::reguard();
    }

    private function seedTaxClasses(): void
    {
        $taxClass = TaxClass::query()->create([
            'name' => 'Standard',
            'is_default' => true,
        ]);

        $taxClass->countries()->attach(Country::where('iso_2', 'IT')->value('id'), [
            'rate' => 22,
        ]);
    }

    private function seedProductTypes(): void
    {
        ProductType::query()->create([
            'name' => 'Default',
            'active' => true,
            'is_default' => true,
        ]);
    }
};
