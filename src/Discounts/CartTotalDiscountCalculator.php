<?php

namespace PictaStudio\Venditio\Discounts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use PictaStudio\Venditio\Contracts\{CartTotalDiscountCalculatorInterface, DiscountRuleInterface, DiscountablesResolverInterface};
use PictaStudio\Venditio\Enums\DiscountType;
use PictaStudio\Venditio\Models\Discount;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class CartTotalDiscountCalculator implements CartTotalDiscountCalculatorInterface
{
    public function __construct(
        private readonly DiscountablesResolverInterface $discountablesResolver,
    ) {}

    public function resolveForTarget(Model $target, Collection $lines, DiscountContext $context): array
    {
        $code = $target->getAttribute('discount_code');

        if (blank($code)) {
            return $this->emptyResult();
        }

        /** @var Discount|null $discount */
        $discount = $this->loadDiscountByCode((string) $code);

        if (!$discount instanceof Discount) {
            return $this->emptyResult();
        }

        if (!$discount->apply_to_cart_total) {
            return $this->emptyResult();
        }

        $eligibleLines = $this->discountableLines($lines);

        if ($eligibleLines->isEmpty()) {
            return $this->emptyResult();
        }

        if (!$this->belongsToResolvedDiscountables($discount, $eligibleLines, $context)) {
            return $this->emptyResult();
        }

        $baseAmount = $this->resolveBaseAmount($target, $eligibleLines);

        if (!$this->meetsMinimumOrderTotal($discount, $baseAmount)) {
            return $this->emptyResult();
        }

        if (!$this->passesRules($discount, $target, $context)) {
            return $this->emptyResult();
        }

        $discountAmount = $this->calculateDiscountAmount($discount, $baseAmount);

        if ($discountAmount <= 0 && !$discount->free_shipping) {
            return $this->emptyResult();
        }

        return [
            'discount_id' => $discount->getKey(),
            'discount_code' => $discount->code,
            'discount_amount' => $discountAmount,
            'free_shipping' => (bool) $discount->free_shipping,
        ];
    }

    private function loadDiscountByCode(string $code): ?Discount
    {
        $discountModel = resolve_model('discount');

        return $discountModel::query()
            ->withoutGlobalScopes()
            ->where('code', $code)
            ->first();
    }

    private function belongsToResolvedDiscountables(Discount $discount, Collection $lines, DiscountContext $context): bool
    {
        if (blank($discount->discountable_type) || blank($discount->discountable_id)) {
            return true;
        }

        $discountables = $lines
            ->flatMap(fn (Model $line) => $this->discountablesResolver->resolve($line, $context))
            ->filter(fn (mixed $model) => $model instanceof Model)
            ->push($context->getCart())
            ->push($context->getOrder())
            ->push($context->getUser())
            ->filter(fn (mixed $model) => $model instanceof Model && filled($model->getKey()))
            ->unique(fn (Model $model) => implode(':', [$model->getMorphClass(), (string) $model->getKey()]));

        return $discountables->contains(
            fn (Model $model) => $model->getMorphClass() === $discount->discountable_type
                && (string) $model->getKey() === (string) $discount->discountable_id
        );
    }

    private function passesRules(Discount $discount, Model $target, DiscountContext $context): bool
    {
        $ruleClasses = config('venditio.discounts.cart_total.rules', []);

        foreach ($ruleClasses as $ruleClass) {
            /** @var DiscountRuleInterface $rule */
            $rule = app($ruleClass);

            if (!$rule->passes($discount, $target, $context)) {
                return false;
            }
        }

        return true;
    }

    private function resolveBaseAmount(Model $target, Collection $lines): float
    {
        $base = config('venditio.discounts.cart_total.base', 'subtotal');

        $subTotal = (float) $lines->sum('total_final_price');
        $shippingFee = (float) ($target->getAttribute('shipping_fee') ?? 0);
        $paymentFee = (float) ($target->getAttribute('payment_fee') ?? 0);

        return match ($base) {
            'checkout_total' => max(0, $subTotal + $shippingFee + $paymentFee),
            default => max(0, $subTotal),
        };
    }

    private function calculateDiscountAmount(Discount $discount, float $baseAmount): float
    {
        if ($baseAmount <= 0) {
            return 0;
        }

        $rawDiscount = match ($discount->type) {
            DiscountType::Percentage => $baseAmount * ((float) $discount->value / 100),
            DiscountType::Fixed => (float) $discount->value,
            default => 0,
        };

        return round(min($baseAmount, max(0, $rawDiscount)), 2);
    }

    private function meetsMinimumOrderTotal(Discount $discount, float $baseAmount): bool
    {
        $minimumOrderTotal = (float) ($discount->minimum_order_total ?? 0);

        if ($minimumOrderTotal <= 0) {
            return true;
        }

        return $baseAmount >= $minimumOrderTotal;
    }

    private function emptyResult(): array
    {
        return [
            'discount_id' => null,
            'discount_code' => null,
            'discount_amount' => 0.0,
            'free_shipping' => false,
        ];
    }

    private function discountableLines(Collection $lines): Collection
    {
        return $lines
            ->filter(fn (mixed $line) => $line instanceof Model && $this->lineAllowsDiscounts($line))
            ->values();
    }

    private function lineAllowsDiscounts(Model $line): bool
    {
        $productData = $line->getAttribute('product_data');

        if (!is_array($productData)) {
            return true;
        }

        $allowDiscounts = data_get(
            $productData,
            'price_calculated.price_source.price_list.allow_discounts',
            data_get(
                $productData,
                'pricing.price_source.price_list.allow_discounts',
                data_get(
                    $productData,
                    'price_calculated.price_list.allow_discounts',
                    data_get(
                        $productData,
                        'pricing.price_list.allow_discounts',
                        data_get(
                            $productData,
                            'price_calculated.price_source.allow_discounts',
                            data_get($productData, 'pricing.price_source.allow_discounts', true)
                        )
                    )
                )
            )
        );

        return filter_var($allowDiscounts, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
    }
}
