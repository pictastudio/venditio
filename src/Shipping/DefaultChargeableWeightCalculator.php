<?php

namespace PictaStudio\Venditio\Shipping;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use PictaStudio\Venditio\Contracts\ChargeableWeightCalculatorInterface;

class DefaultChargeableWeightCalculator implements ChargeableWeightCalculatorInterface
{
    public function calculate(Collection $lines, Model $shippingCarrier): array
    {
        $actualWeight = 0.0;
        $volumetricWeight = 0.0;

        $divisor = max(
            0.01,
            (float) ($shippingCarrier->getAttribute('volumetric_divisor')
                ?? config('venditio.shipping.defaults.volumetric_divisor', 5000))
        );

        foreach ($lines as $line) {
            $qty = max(0, (int) ($line->getAttribute('qty') ?? 0));
            $productData = $line->getAttribute('product_data') ?? [];

            $weight = max(0, (float) data_get($productData, 'weight', 0));
            $length = max(0, (float) data_get($productData, 'length', 0));
            $width = max(0, (float) data_get($productData, 'width', 0));
            $height = max(0, (float) data_get($productData, 'height', 0));

            $actualWeight += ($qty * $weight);

            $lineVolumeCm3 = $length * $width * $height;
            $volumetricWeight += ($qty * ($lineVolumeCm3 / $divisor));
        }

        $chargeable = max($actualWeight, $volumetricWeight);

        return [
            'actual_weight_kg' => round($actualWeight, 3),
            'volumetric_weight_kg' => round($volumetricWeight, 3),
            'chargeable_weight_kg' => $this->roundChargeableWeight($chargeable, $shippingCarrier),
        ];
    }

    private function roundChargeableWeight(float $weight, Model $shippingCarrier): float
    {
        $step = max(
            0.001,
            (float) ($shippingCarrier->getAttribute('weight_rounding_step_kg')
                ?? config('venditio.shipping.defaults.weight_rounding_step_kg', 0.5))
        );

        $mode = (string) ($shippingCarrier->getAttribute('weight_rounding_mode')
            ?? config('venditio.shipping.defaults.weight_rounding_mode', 'ceil'));

        $units = $weight / $step;

        $roundedUnits = match ($mode) {
            'floor' => floor($units),
            'round' => round($units),
            default => ceil($units),
        };

        return round(max(0, $roundedUnits * $step), 3);
    }
}
