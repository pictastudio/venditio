<?php

namespace PictaStudio\Venditio\Shipping;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use PictaStudio\Venditio\Contracts\ShippingZoneResolverInterface;
use PictaStudio\Venditio\Models\ShippingZone;

use function PictaStudio\Venditio\Helpers\Functions\query;

class DefaultShippingZoneResolver implements ShippingZoneResolverInterface
{
    public function resolve(Model $cart, Model $shippingMethod): array
    {
        $destination = $this->resolveDestination($cart);

        if (!$destination['has_specific_destination']) {
            return [
                'shipping_zone' => null,
                'shipping_method_zone' => null,
                'matched_scope' => null,
            ];
        }

        $zones = query('shipping_zone')
            ->where('active', true)
            ->whereHas('shippingMethodZones', function ($query) use ($shippingMethod): void {
                $query
                    ->where('shipping_method_id', $shippingMethod->getKey())
                    ->where('active', true);
            })
            ->with([
                'countries:id',
                'regions:id,country_id',
                'provinces:id,region_id',
                'shippingMethodZones' => fn ($query) => $query
                    ->where('shipping_method_id', $shippingMethod->getKey())
                    ->where('active', true),
            ])
            ->get();

        $candidates = $zones
            ->map(fn (ShippingZone $shippingZone): array => [
                'shipping_zone' => $shippingZone,
                'shipping_method_zone' => $shippingZone->shippingMethodZones->first(),
                'matched_scope' => $this->matchedScope($shippingZone, $destination),
            ])
            ->filter(fn (array $candidate): bool => $candidate['matched_scope'] !== null)
            ->values();

        if ($candidates->isEmpty()) {
            if ($destination['province_id'] !== null) {
                throw ValidationException::withMessages([
                    'shipping_method_id' => ['The selected shipping method is not available for the provided destination.'],
                ]);
            }

            return [
                'shipping_zone' => null,
                'shipping_method_zone' => null,
                'matched_scope' => null,
            ];
        }

        $resolved = $candidates
            ->sort(function (array $left, array $right): int {
                $specificityComparison = $this->scopeSpecificity($right['matched_scope']) <=> $this->scopeSpecificity($left['matched_scope']);

                if ($specificityComparison !== 0) {
                    return $specificityComparison;
                }

                $priorityComparison = (int) ($right['shipping_zone']->priority ?? 0) <=> (int) ($left['shipping_zone']->priority ?? 0);

                if ($priorityComparison !== 0) {
                    return $priorityComparison;
                }

                return (int) $left['shipping_zone']->getKey() <=> (int) $right['shipping_zone']->getKey();
            })
            ->first();

        return $resolved ?? [
            'shipping_zone' => null,
            'shipping_method_zone' => null,
            'matched_scope' => null,
        ];
    }

    private function resolveDestination(Model $cart): array
    {
        $shippingAddress = data_get($cart->getAttribute('addresses'), 'shipping', []);
        $countryId = is_numeric(data_get($shippingAddress, 'country_id'))
            ? (int) data_get($shippingAddress, 'country_id')
            : null;
        $provinceId = is_numeric(data_get($shippingAddress, 'province_id'))
            ? (int) data_get($shippingAddress, 'province_id')
            : null;
        $stateCode = data_get($shippingAddress, 'state');

        if ($provinceId === null && is_string($stateCode) && filled($stateCode)) {
            $provinceId = $this->findProvinceIdByCode($stateCode, $countryId);
        }

        $regionId = null;

        if ($provinceId !== null) {
            $province = query('province')
                ->with('region')
                ->find($provinceId);

            if ($province instanceof Model) {
                $provinceId = (int) $province->getKey();
                $regionId = is_numeric($province->region_id) ? (int) $province->region_id : null;
                $countryId = $countryId ?? (is_numeric(optional($province->region)->country_id) ? (int) optional($province->region)->country_id : null);
            }
        }

        return [
            'country_id' => $countryId,
            'region_id' => $regionId,
            'province_id' => $provinceId,
            'has_specific_destination' => $provinceId !== null || $countryId !== null,
        ];
    }

    private function findProvinceIdByCode(string $stateCode, ?int $countryId): ?int
    {
        $provinceQuery = query('province')
            ->where('code', mb_strtoupper($stateCode));

        if ($countryId !== null) {
            $provinceQuery->whereHas('region', fn ($query) => $query->where('country_id', $countryId));
        }

        $province = $provinceQuery->first();

        return $province instanceof Model ? (int) $province->getKey() : null;
    }

    private function matchedScope(ShippingZone $shippingZone, array $destination): ?string
    {
        $provinceId = $destination['province_id'];
        $regionId = $destination['region_id'];
        $countryId = $destination['country_id'];

        if ($provinceId !== null && $this->relationIds($shippingZone->provinces)->contains($provinceId)) {
            return 'province';
        }

        if ($regionId !== null && $this->relationIds($shippingZone->regions)->contains($regionId)) {
            return 'region';
        }

        if ($countryId !== null && $this->relationIds($shippingZone->countries)->contains($countryId)) {
            return 'country';
        }

        return null;
    }

    private function relationIds(Collection $models): Collection
    {
        return $models
            ->map(fn (Model $model): int => (int) $model->getKey());
    }

    private function scopeSpecificity(?string $scope): int
    {
        return match ($scope) {
            'province' => 3,
            'region' => 2,
            'country' => 1,
            default => 0,
        };
    }
}
