<?php

namespace PictaStudio\Venditio\Shipping;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use PictaStudio\Venditio\Contracts\ShippingZoneMatcherInterface;

use function PictaStudio\Venditio\Helpers\Functions\query;

class MostSpecificZoneMatcher implements ShippingZoneMatcherInterface
{
    public function match(array $shippingAddress): ?Model
    {
        $candidates = [
            ['type' => 'municipality', 'id' => $shippingAddress['municipality_id'] ?? null],
            ['type' => 'province', 'id' => $shippingAddress['province_id'] ?? null],
            ['type' => 'region', 'id' => $shippingAddress['region_id'] ?? null],
            ['type' => 'country', 'id' => $shippingAddress['country_id'] ?? null],
        ];

        foreach ($candidates as $candidate) {
            $zoneableId = $candidate['id'];

            if (blank($zoneableId)) {
                continue;
            }

            $zone = query('shipping_zone')
                ->where('active', true)
                ->whereHas('members', function (Builder $builder) use ($candidate, $zoneableId): void {
                    $builder
                        ->where('zoneable_type', $candidate['type'])
                        ->where('zoneable_id', (int) $zoneableId);
                })
                ->orderByDesc('priority')
                ->orderBy('id')
                ->first();

            if ($zone instanceof Model) {
                return $zone;
            }
        }

        return query('shipping_zone')
            ->where('active', true)
            ->where('is_fallback', true)
            ->orderByDesc('priority')
            ->orderBy('id')
            ->first();
    }
}
