<?php

namespace PictaStudio\Venditio\Actions\Discounts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use PictaStudio\Venditio\Models\Discount;

use function PictaStudio\Venditio\Helpers\Functions\query;

class UpsertMultipleDiscounts
{
    public function handle(array $discounts): Collection
    {
        return DB::transaction(function () use ($discounts): Collection {
            $upsertedDiscounts = new Collection;

            foreach ($discounts as $discountPayload) {
                $discountId = array_key_exists('id', $discountPayload)
                    ? (int) $discountPayload['id']
                    : null;

                if ($discountId !== null) {
                    /** @var Discount $discount */
                    $discount = query('discount')
                        ->withTrashed()
                        ->whereKey($discountId)
                        ->firstOrFail();

                    $discount->fill(collect($discountPayload)->except('id')->all());
                    $discount->save();

                    if ($discount->trashed()) {
                        $discount->restore();
                    }

                    $upsertedDiscounts->push($discount->refresh());

                    continue;
                }

                /** @var Discount $discount */
                $discount = query('discount')->create($discountPayload);
                $upsertedDiscounts->push($discount->refresh());
            }

            return $upsertedDiscounts;
        });
    }
}
