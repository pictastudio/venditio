<?php

namespace PictaStudio\Venditio\Actions\ShippingZones;

use Illuminate\Support\Arr;
use PictaStudio\Venditio\Models\ShippingZone;

class UpdateShippingZone
{
    public function handle(ShippingZone $shippingZone, array $payload): ShippingZone
    {
        $countryIdsProvided = array_key_exists('country_ids', $payload);
        $regionIdsProvided = array_key_exists('region_ids', $payload);
        $provinceIdsProvided = array_key_exists('province_ids', $payload);

        $countryIds = $this->normalizeIds(Arr::pull($payload, 'country_ids', []));
        $regionIds = $this->normalizeIds(Arr::pull($payload, 'region_ids', []));
        $provinceIds = $this->normalizeIds(Arr::pull($payload, 'province_ids', []));

        $shippingZone->fill($payload);
        $shippingZone->save();

        if ($countryIdsProvided) {
            $shippingZone->countries()->sync($countryIds);
        }

        if ($regionIdsProvided) {
            $shippingZone->regions()->sync($regionIds);
        }

        if ($provinceIdsProvided) {
            $shippingZone->provinces()->sync($provinceIds);
        }

        return $shippingZone->refresh();
    }

    private function normalizeIds(array $ids): array
    {
        return collect($ids)
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
