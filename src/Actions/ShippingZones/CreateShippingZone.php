<?php

namespace PictaStudio\Venditio\Actions\ShippingZones;

use Illuminate\Support\Arr;
use PictaStudio\Venditio\Models\ShippingZone;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class CreateShippingZone
{
    public function handle(array $payload): ShippingZone
    {
        $countryIds = $this->normalizeIds(Arr::pull($payload, 'country_ids', []));
        $regionIds = $this->normalizeIds(Arr::pull($payload, 'region_ids', []));
        $provinceIds = $this->normalizeIds(Arr::pull($payload, 'province_ids', []));

        /** @var ShippingZone $shippingZone */
        $shippingZone = resolve_model('shipping_zone')::query()->create($payload);

        $shippingZone->countries()->sync($countryIds);
        $shippingZone->regions()->sync($regionIds);
        $shippingZone->provinces()->sync($provinceIds);

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
