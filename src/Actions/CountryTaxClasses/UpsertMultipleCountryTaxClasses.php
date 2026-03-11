<?php

namespace PictaStudio\Venditio\Actions\CountryTaxClasses;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use PictaStudio\Venditio\Models\CountryTaxClass;

use function PictaStudio\Venditio\Helpers\Functions\query;

class UpsertMultipleCountryTaxClasses
{
    public function handle(array $countryTaxClasses): Collection
    {
        return DB::transaction(function () use ($countryTaxClasses): Collection {
            $upsertedCountryTaxClasses = new Collection;

            foreach ($countryTaxClasses as $countryTaxClassPayload) {
                $identifiers = [
                    'country_id' => (int) $countryTaxClassPayload['country_id'],
                    'tax_class_id' => (int) $countryTaxClassPayload['tax_class_id'],
                ];

                $attributes = [
                    'rate' => $countryTaxClassPayload['rate'],
                ];

                /** @var CountryTaxClass $countryTaxClass */
                $countryTaxClass = query('country_tax_class')->updateOrCreate($identifiers, $attributes);
                $upsertedCountryTaxClasses->push($countryTaxClass->refresh());
            }

            return $upsertedCountryTaxClasses;
        });
    }
}
