<?php

namespace PictaStudio\Venditio\Shipping;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Fluent;
use PictaStudio\Venditio\Contracts\{ChargeableWeightCalculatorInterface, ShippingQuoteCalculatorInterface, ShippingZoneMatcherInterface};

use function PictaStudio\Venditio\Helpers\Functions\query;

class DefaultShippingQuoteCalculator implements ShippingQuoteCalculatorInterface
{
    public function __construct(
        private readonly ShippingZoneMatcherInterface $zoneMatcher,
        private readonly ChargeableWeightCalculatorInterface $chargeableWeightCalculator,
    ) {}

    public function calculateForCart(Model $cart): Collection
    {
        if (!config('venditio.shipping.enabled', true)) {
            return collect();
        }

        $shippingAddress = $this->extractShippingAddress($cart);
        $zone = $this->zoneMatcher->match($shippingAddress);

        if (!$zone instanceof Model) {
            return collect();
        }

        $resolver = fn () => $this->buildQuotes($cart, $zone);

        if (!$this->shouldUseCache($cart)) {
            return $resolver();
        }

        return Cache::remember(
            $this->cacheKey($cart, $zone, $shippingAddress),
            now()->addSeconds((int) config('venditio.shipping.cache.ttl_seconds', 300)),
            $resolver,
        );
    }

    private function buildQuotes(Model $cart, Model $zone): Collection
    {
        $lines = $cart->relationLoaded('lines')
            ? collect($cart->getRelation('lines'))
            : $cart->lines()->get();

        $subtotal = (float) $lines->sum(fn (Model $line) => (float) $line->getAttribute('total_final_price'));

        $rates = query('shipping_rate')
            ->with(['shippingCarrier', 'shippingZone', 'tiers'])
            ->where('active', true)
            ->where('shipping_zone_id', (int) $zone->getKey())
            ->whereHas('shippingCarrier', fn (Builder $builder) => $builder->where('active', true))
            ->where(fn (Builder $builder) => $builder
                ->whereNull('min_order_subtotal')
                ->orWhere('min_order_subtotal', '<=', $subtotal)
            )
            ->where(fn (Builder $builder) => $builder
                ->whereNull('max_order_subtotal')
                ->orWhere('max_order_subtotal', '>=', $subtotal)
            )
            ->get();

        $quotes = $rates
            ->filter(fn (Model $rate) => $rate->relationLoaded('shippingCarrier') && $rate->shippingCarrier instanceof Model)
            ->map(function (Model $rate) use ($lines, $zone) {
                $carrier = $rate->shippingCarrier;
                $weights = $this->chargeableWeightCalculator->calculate($lines, $carrier);
                $tierFee = $this->resolveTierFee($rate, (float) $weights['chargeable_weight_kg']);

                if ($tierFee === null) {
                    return null;
                }

                $baseFee = (float) ($rate->getAttribute('base_fee') ?? 0);

                return [
                    'shipping_rate_id' => (int) $rate->getKey(),
                    'shipping_carrier_id' => (int) $carrier->getKey(),
                    'shipping_zone_id' => (int) $zone->getKey(),
                    'actual_weight_kg' => (float) $weights['actual_weight_kg'],
                    'volumetric_weight_kg' => (float) $weights['volumetric_weight_kg'],
                    'chargeable_weight_kg' => (float) $weights['chargeable_weight_kg'],
                    'base_fee' => round($baseFee, 2),
                    'tier_fee' => round($tierFee, 2),
                    'amount' => round($baseFee + $tierFee, 2),
                    'carrier' => [
                        'id' => (int) $carrier->getKey(),
                        'code' => (string) $carrier->getAttribute('code'),
                        'name' => (string) $carrier->getAttribute('name'),
                    ],
                    'zone' => [
                        'id' => (int) $zone->getKey(),
                        'code' => (string) $zone->getAttribute('code'),
                        'name' => (string) $zone->getAttribute('name'),
                    ],
                ];
            })
            ->filter(fn (mixed $quote): bool => is_array($quote));

        return $quotes
            ->sortBy([
                ['amount', 'asc'],
                ['shipping_rate_id', 'asc'],
            ])
            ->values();
    }

    private function resolveTierFee(Model $rate, float $chargeableWeightKg): ?float
    {
        $tiers = $rate->relationLoaded('tiers')
            ? collect($rate->getRelation('tiers'))
            : $rate->tiers()->get();

        if ($tiers->isEmpty()) {
            return 0.0;
        }

        $tier = $tiers->first(function (Model $tier) use ($chargeableWeightKg): bool {
            $from = (float) ($tier->getAttribute('from_weight_kg') ?? 0);
            $to = $tier->getAttribute('to_weight_kg');

            return $chargeableWeightKg >= $from
                && ($to === null || $chargeableWeightKg < (float) $to);
        });

        if (!$tier instanceof Model) {
            return null;
        }

        return (float) ($tier->getAttribute('additional_fee') ?? 0);
    }

    private function extractShippingAddress(Model $cart): array
    {
        $addresses = $cart->getAttribute('addresses');

        if ($addresses instanceof Fluent) {
            $addresses = $addresses->toArray();
        }

        if (!is_array($addresses)) {
            return [];
        }

        $shippingAddress = $addresses['shipping'] ?? [];

        return is_array($shippingAddress) ? $shippingAddress : [];
    }

    private function shouldUseCache(Model $cart): bool
    {
        return (bool) config('venditio.shipping.cache.enabled', true)
            && filled($cart->getKey());
    }

    private function cacheKey(Model $cart, Model $zone, array $shippingAddress): string
    {
        $prefix = (string) config('venditio.shipping.cache.prefix', 'venditio:shipping');
        $updatedAt = (string) ($cart->getAttribute('updated_at')?->timestamp ?? 0);

        $lines = $cart->relationLoaded('lines')
            ? collect($cart->getRelation('lines'))
            : $cart->lines()->get();

        $linesSignature = $lines
            ->map(fn (Model $line) => [
                'id' => $line->getKey(),
                'qty' => (int) ($line->getAttribute('qty') ?? 0),
                'price' => (float) ($line->getAttribute('total_final_price') ?? 0),
                'product_data' => $line->getAttribute('product_data'),
            ])
            ->toJson();

        return implode(':', [
            $prefix,
            'cart',
            (int) $cart->getKey(),
            $updatedAt,
            (int) $zone->getKey(),
            md5(json_encode($shippingAddress) ?: ''),
            md5($linesSignature),
        ]);
    }
}
