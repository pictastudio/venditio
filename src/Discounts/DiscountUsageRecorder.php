<?php

namespace PictaStudio\Venditio\Discounts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use PictaStudio\Venditio\Contracts\DiscountUsageRecorderInterface;
use PictaStudio\Venditio\Models\Discount;

use function PictaStudio\Venditio\Helpers\Functions\{get_fresh_model_instance, resolve_model};

class DiscountUsageRecorder implements DiscountUsageRecorderInterface
{
    public function recordFromOrder(Model $order): void
    {
        $lines = $order->relationLoaded('lines')
            ? $order->getRelation('lines')
            : $order->lines;

        $discountCodes = $lines
            ->flatMap(fn (Model $line) => $this->extractDiscountCodesFromLine($line))
            ->push($order->getAttribute('discount_code'))
            ->filter(fn (mixed $code) => filled($code))
            ->unique()
            ->values();

        if ($discountCodes->isEmpty()) {
            return;
        }

        $discounts = $this->loadDiscountsByCode($discountCodes);
        $increments = [];
        $usageModel = resolve_model('discount_application');

        $lines->each(function (Model $line) use ($order, $discounts, $usageModel, &$increments) {
            $this->resolveLineDiscountUsages($line, $discounts)
                ->each(function (array $lineUsage) use ($line, $order, $usageModel, &$increments) {
                    /** @var Discount $discount */
                    $discount = $lineUsage['discount'];

                    $usage = $usageModel::query()->firstOrCreate(
                        [
                            'discount_id' => $discount->getKey(),
                            'order_line_id' => $line->getKey(),
                        ],
                        [
                            'discountable_type' => get_fresh_model_instance('product')->getMorphClass(),
                            'discountable_id' => $line->getAttribute('product_id'),
                            'user_id' => $order->getAttribute('user_id'),
                            'cart_id' => $order->getRelation('sourceCart')?->getKey(),
                            'order_id' => $order->getKey(),
                            'qty' => (int) ($line->getAttribute('qty') ?? 1),
                            'amount' => $lineUsage['amount'],
                        ]
                    );

                    if ($usage->wasRecentlyCreated) {
                        $discountId = $discount->getKey();
                        $increments[$discountId] = ($increments[$discountId] ?? 0) + 1;
                    }
                });
        });

        $this->recordOrderLevelDiscountUsage($order, $discounts, $increments);
        $this->incrementDiscountUses($increments);
    }

    /**
     * @param  Collection<int, string>  $discountCodes
     * @return Collection<string, Discount>
     */
    private function loadDiscountsByCode(Collection $discountCodes): Collection
    {
        $discountModel = resolve_model('discount');

        return $discountModel::query()
            ->withoutGlobalScopes()
            ->whereIn('code', $discountCodes->all())
            ->get()
            ->keyBy('code');
    }

    private function incrementDiscountUses(array $increments): void
    {
        if (blank($increments)) {
            return;
        }

        $discountModel = resolve_model('discount');

        foreach ($increments as $discountId => $uses) {
            $discountModel::query()
                ->withoutGlobalScopes()
                ->whereKey($discountId)
                ->increment('uses', $uses);
        }
    }

    /**
     * @return Collection<int, string>
     */
    private function extractDiscountCodesFromLine(Model $line): Collection
    {
        $snapshotCodes = collect(data_get($line->getAttribute('product_data'), 'price_calculated.discounts_applied', []))
            ->map(fn (mixed $entry) => is_array($entry) ? ($entry['code'] ?? null) : null)
            ->filter(fn (mixed $code) => filled($code))
            ->values();

        return collect([$line->getAttribute('discount_code')])
            ->merge($snapshotCodes)
            ->filter(fn (mixed $code) => filled($code))
            ->values();
    }

    /**
     * @param  Collection<string, Discount>  $discounts
     * @return Collection<int, array{discount:Discount,amount:float}>
     */
    private function resolveLineDiscountUsages(Model $line, Collection $discounts): Collection
    {
        $snapshotUsages = collect(data_get($line->getAttribute('product_data'), 'price_calculated.discounts_applied', []))
            ->map(function (mixed $entry) use ($discounts): ?array {
                if (!is_array($entry) || blank($entry['code'] ?? null)) {
                    return null;
                }

                /** @var Discount|null $discount */
                $discount = $discounts->get($entry['code']);

                if (!$discount instanceof Discount) {
                    return null;
                }

                return [
                    'discount' => $discount,
                    'amount' => round((float) ($entry['amount'] ?? 0), 2),
                ];
            })
            ->filter(fn (mixed $entry) => is_array($entry))
            ->values();

        if ($snapshotUsages->isNotEmpty()) {
            return $snapshotUsages;
        }

        $discountCode = $line->getAttribute('discount_code');

        if (blank($discountCode)) {
            return collect();
        }

        /** @var Discount|null $discount */
        $discount = $discounts->get($discountCode);

        if (!$discount instanceof Discount) {
            return collect();
        }

        return collect([[
            'discount' => $discount,
            'amount' => round((float) ($line->getAttribute('discount_amount') ?? 0), 2),
        ]]);
    }

    /**
     * @param  Collection<string, Discount>  $discounts
     * @param  array<int|string, int>  $increments
     */
    private function recordOrderLevelDiscountUsage(Model $order, Collection $discounts, array &$increments): void
    {
        $orderDiscountCode = $order->getAttribute('discount_code');
        $orderDiscountAmount = (float) ($order->getAttribute('discount_amount') ?? 0);

        if (blank($orderDiscountCode)) {
            return;
        }

        /** @var Discount|null $discount */
        $discount = $discounts->get($orderDiscountCode)
            ?? $this->loadDiscountsByCode(collect([$orderDiscountCode]))->get($orderDiscountCode);

        if (
            !$discount instanceof Discount
            || ($orderDiscountAmount <= 0 && !$discount->free_shipping)
        ) {
            return;
        }

        $usageModel = resolve_model('discount_application');

        $usage = $usageModel::query()->firstOrCreate(
            [
                'discount_id' => $discount->getKey(),
                'order_id' => $order->getKey(),
                'order_line_id' => null,
            ],
            [
                'discountable_type' => $order->getMorphClass(),
                'discountable_id' => $order->getKey(),
                'user_id' => $order->getAttribute('user_id'),
                'cart_id' => $order->getRelation('sourceCart')?->getKey(),
                'qty' => 1,
                'amount' => round($orderDiscountAmount, 2),
            ]
        );

        if ($usage->wasRecentlyCreated) {
            $discountId = $discount->getKey();
            $increments[$discountId] = ($increments[$discountId] ?? 0) + 1;
        }
    }
}
