<?php

namespace PictaStudio\Venditio\Discounts;

use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Support\Collection;
use PictaStudio\Venditio\Contracts\{DiscountCalculatorInterface, DiscountRuleInterface, DiscountablesResolverInterface};
use PictaStudio\Venditio\Enums\DiscountType;
use PictaStudio\Venditio\Models\Discount;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class DiscountCalculator implements DiscountCalculatorInterface
{
    public function __construct(
        private readonly DiscountablesResolverInterface $discountablesResolver,
    ) {}

    public function apply(Model $line, DiscountContext $context): Model
    {
        $unitPrice = (float) $line->getAttribute('unit_price');
        $qty = max(1, (int) ($line->getAttribute('qty') ?? 1));

        $selectedDiscount = $this->resolveApplicableDiscount($line, $context, $unitPrice);
        $unitDiscount = $selectedDiscount instanceof Discount
            ? $this->calculateUnitDiscount($selectedDiscount, $unitPrice)
            : 0.0;
        $unitFinalPrice = max(0, $unitPrice - $unitDiscount);

        $line->fill([
            'discount_id' => $selectedDiscount?->getKey(),
            'discount_code' => $selectedDiscount?->code,
            'discount_amount' => round($unitDiscount * $qty, 2),
            'unit_discount' => $unitDiscount,
            'unit_final_price' => $unitFinalPrice,
        ]);
        $this->syncCalculatedPriceSnapshot($line, $unitFinalPrice);

        if ($selectedDiscount instanceof Discount) {
            $context->markDiscountAsAppliedInCart($selectedDiscount);
        }

        return $line;
    }

    private function syncCalculatedPriceSnapshot(Model $line, float $unitFinalPrice): void
    {
        $productData = $line->getAttribute('product_data');

        if (!is_array($productData)) {
            return;
        }

        if (blank(data_get($productData, 'price_calculated.price'))) {
            data_set($productData, 'price_calculated.price', (float) $line->getAttribute('unit_price'));
        }

        data_set($productData, 'price_calculated.price_final', $unitFinalPrice);
        $line->setAttribute('product_data', $productData);
    }

    private function resolveApplicableDiscount(Model $line, DiscountContext $context, float $unitPrice): ?Discount
    {
        $discounts = $this->queryDiscountsForLine($line, $context);

        if ($discounts->isEmpty()) {
            return null;
        }

        $evaluatedDiscounts = $discounts
            ->filter(fn (Discount $discount) => $this->passesRules($discount, $line, $context))
            ->map(fn (Discount $discount) => [
                'discount' => $discount,
                'amount' => $this->calculateUnitDiscount($discount, $unitPrice),
            ])
            ->filter(fn (array $evaluation) => $evaluation['amount'] > 0)
            ->values();

        if ($evaluatedDiscounts->isEmpty()) {
            return null;
        }

        return $evaluatedDiscounts
            ->sort(fn (array $a, array $b) => $this->sortByPriorityAndAmount($a, $b))
            ->first()['discount'];
    }

    private function queryDiscountsForLine(Model $line, DiscountContext $context): Collection
    {
        $discountModel = resolve_model('discount');
        $discountables = $this->discountablesResolver->resolve($line, $context);

        if ($discountables->isEmpty()) {
            return collect();
        }

        /** @var Builder $query */
        $query = $discountModel::query();

        $query->where(function (Builder $builder) use ($discountables) {
            $discountables->each(function (Model $discountable) use ($builder) {
                $builder->orWhere(function (Builder $query) use ($discountable) {
                    $query->where('discountable_type', $discountable->getMorphClass())
                        ->where('discountable_id', $discountable->getKey());
                });
            });
        });

        return $query->orderByDesc('priority')->get();
    }

    private function passesRules(Discount $discount, Model $line, DiscountContext $context): bool
    {
        $ruleClasses = config('venditio.discounts.rules', []);

        foreach ($ruleClasses as $ruleClass) {
            /** @var DiscountRuleInterface $rule */
            $rule = app($ruleClass);

            if (!$rule->passes($discount, $line, $context)) {
                return false;
            }
        }

        return true;
    }

    private function calculateUnitDiscount(Discount $discount, float $unitPrice): float
    {
        $rawDiscount = match ($discount->type) {
            DiscountType::Percentage => $unitPrice * ((float) $discount->value / 100),
            DiscountType::Fixed => (float) $discount->value,
            default => 0,
        };

        return round(min($unitPrice, max(0, $rawDiscount)), 2);
    }

    private function sortByPriorityAndAmount(array $a, array $b): int
    {
        /** @var Discount $left */
        $left = $a['discount'];
        /** @var Discount $right */
        $right = $b['discount'];

        $leftPriority = (int) $left->priority;
        $rightPriority = (int) $right->priority;

        if ($leftPriority !== $rightPriority) {
            return $rightPriority <=> $leftPriority;
        }

        return $b['amount'] <=> $a['amount'];
    }
}
