<?php

namespace PictaStudio\Venditio\Pipelines\Cart\Pipes;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use PictaStudio\Venditio\Contracts\ShippingQuoteCalculatorInterface;
use PictaStudio\Venditio\Events\ShippingOptionSelected;

class CalculateShippingFees
{
    public function __construct(
        private readonly ShippingQuoteCalculatorInterface $shippingQuoteCalculator,
    ) {}

    public function __invoke(Model $cart, Closure $next): Model
    {
        if (!config('venditio.shipping.enabled', true)) {
            return $next($cart);
        }

        $quotes = $this->shippingQuoteCalculator->calculateForCart($cart);
        $selectedQuote = $this->resolveSelectedQuote(
            $quotes,
            $cart->getAttribute('shipping_rate_id') !== null
                ? (int) $cart->getAttribute('shipping_rate_id')
                : null,
        );

        if (is_array($selectedQuote)) {
            $cart->fill([
                'shipping_fee' => (float) ($selectedQuote['amount'] ?? 0),
                'shipping_rate_id' => isset($selectedQuote['shipping_rate_id']) ? (int) $selectedQuote['shipping_rate_id'] : null,
                'shipping_zone_id' => isset($selectedQuote['shipping_zone_id']) ? (int) $selectedQuote['shipping_zone_id'] : null,
                'shipping_carrier_id' => isset($selectedQuote['shipping_carrier_id']) ? (int) $selectedQuote['shipping_carrier_id'] : null,
                'shipping_quote_snapshot' => $selectedQuote,
            ]);

            event(new ShippingOptionSelected($cart, $selectedQuote));
        } else {
            $cart->fill([
                'shipping_fee' => 0,
                'shipping_rate_id' => null,
                'shipping_zone_id' => null,
                'shipping_carrier_id' => null,
                'shipping_quote_snapshot' => null,
            ]);
        }

        return $next($cart);
    }

    private function resolveSelectedQuote(Collection $quotes, ?int $requestedRateId): ?array
    {
        if ($quotes->isEmpty()) {
            return null;
        }

        if (filled($requestedRateId)) {
            $requestedQuote = $quotes
                ->first(fn (array $quote): bool => (int) $quote['shipping_rate_id'] === $requestedRateId);

            if (is_array($requestedQuote)) {
                return $requestedQuote;
            }
        }

        if (!config('venditio.shipping.auto_select_cheapest', true)) {
            return null;
        }

        $first = $quotes->first();

        return is_array($first) ? $first : null;
    }
}
