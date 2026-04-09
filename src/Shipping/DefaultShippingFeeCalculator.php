<?php

namespace PictaStudio\Venditio\Shipping;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use PictaStudio\Venditio\Contracts\ShippingFeeCalculatorInterface;

class DefaultShippingFeeCalculator implements ShippingFeeCalculatorInterface
{
    public function calculate(string $strategy, Model $cart, ?Model $shippingMethod = null, ?Model $shippingMethodZone = null): float
    {
        return match ($strategy) {
            'flat' => $this->resolveFlatFee($shippingMethod),
            'zones' => $this->resolveZoneFee($cart, $shippingMethodZone),
            default => 0.0,
        };
    }

    private function resolveFlatFee(?Model $shippingMethod): float
    {
        if (!$shippingMethod instanceof Model) {
            return 0.0;
        }

        return round((float) ($shippingMethod->flat_fee ?? 0), 2);
    }

    private function resolveZoneFee(Model $cart, ?Model $shippingMethodZone): float
    {
        if (!$shippingMethodZone instanceof Model) {
            return 0.0;
        }

        $chargeableWeight = (float) ($cart->getAttribute('chargeable_weight') ?? 0);
        $rateTiers = collect($shippingMethodZone->getAttribute('rate_tiers') ?? [])
            ->filter(fn (mixed $rateTier): bool => is_array($rateTier))
            ->sortBy(fn (array $rateTier): float => (float) ($rateTier['max_weight'] ?? 0))
            ->values();

        foreach ($rateTiers as $rateTier) {
            $maxWeight = (float) ($rateTier['max_weight'] ?? 0);

            if ($maxWeight > 0 && $chargeableWeight <= $maxWeight) {
                return round((float) ($rateTier['fee'] ?? 0), 2);
            }
        }

        $overWeightPricePerKg = $shippingMethodZone->getAttribute('over_weight_price_per_kg');

        if (is_numeric($overWeightPricePerKg) && (float) $overWeightPricePerKg >= 0) {
            return round($chargeableWeight * (float) $overWeightPricePerKg, 2);
        }

        throw ValidationException::withMessages([
            'shipping_method_id' => ['No shipping rate is configured for the selected shipping method and destination.'],
        ]);
    }
}
