<?php

namespace PictaStudio\Venditio\Shipping;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use PictaStudio\Venditio\Contracts\ShippingWeightsResolverInterface;

class DefaultShippingWeightsResolver implements ShippingWeightsResolverInterface
{
    public function resolve(Model $cart, ?Model $shippingMethod = null): array
    {
        $lines = $cart->relationLoaded('lines')
            ? $cart->getRelation('lines')
            : $cart->lines;

        if (!$lines instanceof Collection) {
            $lines = collect($lines ?? []);
        }

        $volumetricDivisor = $this->resolveVolumetricDivisor($shippingMethod);

        $specificWeight = (float) $lines->sum(function (Model $line): float {
            return $this->resolveProductWeight($line) * max(1, (int) ($line->qty ?? 1));
        });

        $volumetricWeight = (float) $lines->sum(function (Model $line) use ($volumetricDivisor): float {
            if ($volumetricDivisor <= 0) {
                return 0;
            }

            $length = $this->resolveProductDimension($line, 'length');
            $width = $this->resolveProductDimension($line, 'width');
            $height = $this->resolveProductDimension($line, 'height');

            if ($length <= 0 || $width <= 0 || $height <= 0) {
                return 0;
            }

            return (($length * $width * $height) / $volumetricDivisor) * max(1, (int) ($line->qty ?? 1));
        });

        $specificWeight = round($specificWeight, 2);
        $volumetricWeight = round($volumetricWeight, 2);

        return [
            'specific_weight' => $specificWeight,
            'volumetric_weight' => $volumetricWeight,
            'chargeable_weight' => round(max($specificWeight, $volumetricWeight), 2),
            'volumetric_divisor' => $volumetricDivisor,
        ];
    }

    private function resolveVolumetricDivisor(?Model $shippingMethod): float
    {
        $configuredDivisor = (float) config('venditio.shipping.default_volumetric_divisor', 5000);
        $methodDivisor = $shippingMethod?->getAttribute('volumetric_divisor');

        if (is_numeric($methodDivisor) && (float) $methodDivisor > 0) {
            return (float) $methodDivisor;
        }

        return $configuredDivisor > 0 ? $configuredDivisor : 5000.0;
    }

    private function resolveProductWeight(Model $line): float
    {
        $weight = data_get($line->getAttribute('product_data'), 'weight');

        return is_numeric($weight) ? (float) $weight : 0.0;
    }

    private function resolveProductDimension(Model $line, string $key): float
    {
        $value = data_get($line->getAttribute('product_data'), $key);

        return is_numeric($value) ? (float) $value : 0.0;
    }
}
