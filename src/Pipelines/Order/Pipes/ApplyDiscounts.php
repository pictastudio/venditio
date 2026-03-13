<?php

namespace PictaStudio\Venditio\Pipelines\Order\Pipes;

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

    public function __invoke(Model $order, Closure $next): Model
    {
        $lines = $order->getRelation('lines');

        if (!$lines instanceof Collection) {
            $lines = collect($lines ?? []);
        }

        $sourceCart = $order->getRelation('sourceCart');
        $user = filled($order->getAttribute('user_id'))
            ? query('user')->find($order->getAttribute('user_id'))
            : null;

        $context = DiscountContext::make(
            cart: $sourceCart,
            order: $order,
            user: $user,
        );

        $lines = $lines->map(function (Model $line) use ($context, $order) {
            $line->setRelation('order', $order);
            $this->discountCalculator->apply($line, $context);
            $this->recalculateLineTotals($line);

            return $line;
        });

        $requestedDiscountCode = (string) ($order->getAttribute('discount_code') ?? '');
        $cartTotalDiscount = $this->cartTotalDiscountCalculator->resolveForTarget($order, $lines, $context);
        $appliedDiscountCode = (string) ($cartTotalDiscount['discount_code'] ?? '');

        if (filled($requestedDiscountCode) && $appliedDiscountCode !== $requestedDiscountCode) {
            throw DiscountValidationException::invalidCartTotalDiscountCode($requestedDiscountCode);
        }

        $order->setRelation('lines', $lines);
        $order->fill([
            'discount_amount' => $cartTotalDiscount['discount_amount'],
            'discount_code' => $cartTotalDiscount['discount_code'],
        ]);

        if ((bool) ($cartTotalDiscount['free_shipping'] ?? false)) {
            $order->fill([
                'shipping_fee' => 0,
            ]);
        }

        return $next($order);
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
