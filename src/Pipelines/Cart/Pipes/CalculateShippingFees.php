<?php

namespace PictaStudio\Venditio\Pipelines\Cart\Pipes;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use PictaStudio\Venditio\Contracts\{ShippingFeeCalculatorInterface, ShippingWeightsResolverInterface, ShippingZoneResolverInterface};

use function PictaStudio\Venditio\Helpers\Functions\query;

class CalculateShippingFees
{
    public function __construct(
        private readonly ShippingWeightsResolverInterface $shippingWeightsResolver,
        private readonly ShippingZoneResolverInterface $shippingZoneResolver,
        private readonly ShippingFeeCalculatorInterface $shippingFeeCalculator,
    ) {}

    public function __invoke(Model $cart, Closure $next): Model
    {
        $lines = $cart->getRelation('lines');

        if (!$lines instanceof Collection) {
            $lines = collect($lines ?? []);
        }

        $shippingMethod = $this->resolveShippingMethod($cart);
        $weights = $this->shippingWeightsResolver->resolve($cart, $shippingMethod);

        $cart->fill($weights);
        $cart->setRelation('shippingMethod', $shippingMethod);

        if ($lines->isEmpty()) {
            $cart->fill([
                'shipping_fee' => 0,
                'shipping_zone_id' => null,
            ]);
            $cart->unsetRelation('shippingZone');

            return $next($cart);
        }

        $strategy = $this->resolveStrategy();

        if ($strategy === 'disabled' || !$shippingMethod instanceof Model) {
            $cart->fill([
                'shipping_fee' => 0,
                'shipping_zone_id' => null,
            ]);
            $cart->unsetRelation('shippingZone');

            return $next($cart);
        }

        if ($strategy === 'flat') {
            $cart->fill([
                'shipping_fee' => $this->shippingFeeCalculator->calculate($strategy, $cart, $shippingMethod),
                'shipping_zone_id' => null,
            ]);
            $cart->unsetRelation('shippingZone');

            return $next($cart);
        }

        $resolvedZone = $this->shippingZoneResolver->resolve($cart, $shippingMethod);
        $shippingZone = $resolvedZone['shipping_zone'] ?? null;
        $shippingMethodZone = $resolvedZone['shipping_method_zone'] ?? null;

        if (!$shippingZone instanceof Model || !$shippingMethodZone instanceof Model) {
            $cart->fill([
                'shipping_fee' => 0,
                'shipping_zone_id' => null,
            ]);
            $cart->unsetRelation('shippingZone');

            return $next($cart);
        }

        $cart->setRelation('shippingZone', $shippingZone);
        $cart->fill([
            'shipping_zone_id' => $shippingZone->getKey(),
            'shipping_fee' => $this->shippingFeeCalculator->calculate($strategy, $cart, $shippingMethod, $shippingMethodZone),
        ]);

        return $next($cart);
    }

    private function resolveStrategy(): string
    {
        $strategy = mb_strtolower((string) config('venditio.shipping.strategy', 'disabled'));

        return in_array($strategy, ['disabled', 'flat', 'zones'], true)
            ? $strategy
            : 'disabled';
    }

    private function resolveShippingMethod(Model $cart): ?Model
    {
        $shippingMethodId = $cart->getAttribute('shipping_method_id');

        if (!is_numeric($shippingMethodId)) {
            return null;
        }

        $shippingMethod = query('shipping_method')
            ->where('active', true)
            ->find((int) $shippingMethodId);

        if ($shippingMethod instanceof Model) {
            return $shippingMethod;
        }

        throw ValidationException::withMessages([
            'shipping_method_id' => ['The selected shipping method is not available.'],
        ]);
    }
}
