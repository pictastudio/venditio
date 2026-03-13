<?php

namespace PictaStudio\Venditio\Pipelines\Cart\Pipes;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use PictaStudio\Venditio\Actions\Taxes\ExtractTaxFromGrossPrice;
use PictaStudio\Venditio\Contracts\{CartTotalDiscountCalculatorInterface, DiscountCalculatorInterface};
use PictaStudio\Venditio\Discounts\{DiscountContext, DiscountValidationException};

use function PictaStudio\Venditio\Helpers\Functions\query;

class ApplyDiscounts
{
    public function __construct(
        private readonly DiscountCalculatorInterface $discountCalculator,
        private readonly CartTotalDiscountCalculatorInterface $cartTotalDiscountCalculator,
        private readonly ExtractTaxFromGrossPrice $extractTaxFromGrossPrice,
    ) {}

    /**
     * Called just before cart totals are calculated.
     */
    public function handle(Model $cart, Closure $next): Model
    {
        $lines = $cart->getRelation('lines');

        if (!$lines instanceof Collection) {
            $lines = collect($lines ?? []);
        }

        $context = DiscountContext::make(
            cart: $cart,
            user: $cart->relationLoaded('user')
                ? $cart->user
                : (filled($cart->getAttribute('user_id'))
                    ? query('user')->find($cart->getAttribute('user_id'))
                    : null),
        );

        $lines = $lines->map(function (Model $line) use ($context, $cart) {
            $line->setRelation('cart', $cart);
            $this->discountCalculator->apply($line, $context);
            $this->recalculateLineTotals($line);

            return $line;
        });

        $requestedDiscountCode = (string) ($cart->getAttribute('discount_code') ?? '');
        $cartTotalDiscount = $this->cartTotalDiscountCalculator->resolveForTarget($cart, $lines, $context);
        $appliedDiscountCode = (string) ($cartTotalDiscount['discount_code'] ?? '');

        if (filled($requestedDiscountCode) && $appliedDiscountCode !== $requestedDiscountCode) {
            throw DiscountValidationException::invalidCartTotalDiscountCode($requestedDiscountCode);
        }

        $cart->setRelation('lines', $lines);
        $cart->fill([
            'discount_amount' => $cartTotalDiscount['discount_amount'],
            'discount_code' => $cartTotalDiscount['discount_code'],
        ]);

        if ((bool) ($cartTotalDiscount['free_shipping'] ?? false)) {
            $cart->fill([
                'shipping_fee' => 0,
            ]);
        }

        return $next($cart);
    }

    private function recalculateLineTotals(Model $line): void
    {
        $unitFinalPrice = (float) $line->getAttribute('unit_final_price');
        $taxRate = (float) ($line->getAttribute('tax_rate') ?? 0);
        $qty = max(1, (int) ($line->getAttribute('qty') ?? 1));
        $productData = $line->getAttribute('product_data') ?? [];
        $priceIncludesTax = (bool) data_get($productData, 'inventory.price_includes_tax', true);

        if ($priceIncludesTax) {
            $taxBreakdown = $this->extractTaxFromGrossPrice->handle($unitFinalPrice, $taxRate);
            $unitFinalPriceTaxable = $taxBreakdown['taxable'];
            $unitFinalPriceTax = $taxBreakdown['tax'];
        } else {
            $unitFinalPriceTaxable = $unitFinalPrice;
            $unitFinalPriceTax = round($unitFinalPrice * ($taxRate / 100), 2);
        }

        $line->fill([
            'unit_final_price_taxable' => $unitFinalPriceTaxable,
            'unit_final_price_tax' => $unitFinalPriceTax,
            'total_final_price' => round(($unitFinalPriceTaxable + $unitFinalPriceTax) * $qty, 2),
        ]);
    }
}
