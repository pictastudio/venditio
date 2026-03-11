<?php

namespace PictaStudio\Venditio\Actions\Taxes;

use function PictaStudio\Venditio\Helpers\Functions\query;

class ResolveTaxRate
{
    public function handle(mixed $taxClassId, ?int $countryId = null, ?string $countryIso2 = null): float
    {
        if (!is_numeric($taxClassId)) {
            return 0.0;
        }

        $query = query('country_tax_class')
            ->where('tax_class_id', (int) $taxClassId);

        $resolvedCountryId = $countryId ?? $this->resolveCountryIdByIso2($countryIso2);

        if ($resolvedCountryId !== null) {
            $countryRate = (clone $query)
                ->where('country_id', $resolvedCountryId)
                ->value('rate');

            if ($countryRate !== null) {
                return (float) $countryRate;
            }
        }

        return (float) ($query->value('rate') ?? 0);
    }

    private function resolveCountryIdByIso2(?string $countryIso2): ?int
    {
        if (!is_string($countryIso2) || mb_trim($countryIso2) === '') {
            return null;
        }

        $countryId = query('country')
            ->where('iso_2', mb_strtoupper(mb_trim($countryIso2)))
            ->value('id');

        return is_numeric($countryId) ? (int) $countryId : null;
    }
}
