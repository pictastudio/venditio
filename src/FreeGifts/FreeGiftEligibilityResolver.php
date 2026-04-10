<?php

namespace PictaStudio\Venditio\FreeGifts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use PictaStudio\Venditio\Enums\CartFreeGiftDecisionType;
use PictaStudio\Venditio\Models\FreeGift;

use function PictaStudio\Venditio\Helpers\Functions\query;

class FreeGiftEligibilityResolver
{
    public function resolveForCart(
        Model $cart,
        ?Collection $purchasedLines = null,
        ?Collection $giftLines = null,
    ): Collection {
        $purchasedLines ??= $this->resolvePurchasedLines($cart);
        $giftLines ??= $this->resolveGiftLines($cart);
        $decisionsByFreeGiftId = $this->resolveDecisionsByFreeGiftId($cart);
        $purchasedProductIds = $purchasedLines
            ->pluck('product_id')
            ->filter(fn (mixed $productId): bool => filled($productId))
            ->map(fn (mixed $productId): int => (int) $productId)
            ->unique()
            ->values();
        $subtotal = round((float) $purchasedLines->sum('total_final_price'), 2);
        $quantity = (int) $purchasedLines->sum(fn (mixed $line): int => (int) data_get($line, 'qty', 0));
        $userId = filled($cart->getAttribute('user_id'))
            ? (int) $cart->getAttribute('user_id')
            : null;

        return query('free_gift')
            ->with([
                'qualifyingUsers:id',
                'qualifyingProducts:id',
                'giftProducts.inventory',
            ])
            ->where('active', true)
            ->where('starts_at', '<=', now())
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            })
            ->get()
            ->filter(function (FreeGift $freeGift) use ($giftLines, $decisionsByFreeGiftId, $purchasedProductIds, $quantity, $subtotal, $userId): bool {
                return $this->matchesUser($freeGift, $userId)
                    && $this->matchesSubtotal($freeGift, $subtotal)
                    && $this->matchesQuantity($freeGift, $quantity)
                    && $this->matchesProducts($freeGift, $purchasedProductIds)
                    && $freeGift->giftProducts->isNotEmpty();
            })
            ->map(function (FreeGift $freeGift) use ($giftLines, $decisionsByFreeGiftId): FreeGift {
                $giftProductIds = $freeGift->giftProducts
                    ->pluck('id')
                    ->map(fn (mixed $id): int => (int) $id)
                    ->unique()
                    ->values();
                $decisions = $decisionsByFreeGiftId->get((int) $freeGift->getKey(), collect());
                $selectedProductIds = $decisions
                    ->filter(fn (Model $decision): bool => $this->decisionValue($decision->decision) === CartFreeGiftDecisionType::Selected->value)
                    ->pluck('product_id')
                    ->map(fn (mixed $id): int => (int) $id)
                    ->intersect($giftProductIds)
                    ->values()
                    ->all();
                $declinedProductIds = $decisions
                    ->filter(fn (Model $decision): bool => $this->decisionValue($decision->decision) === CartFreeGiftDecisionType::Declined->value)
                    ->pluck('product_id')
                    ->map(fn (mixed $id): int => (int) $id)
                    ->intersect($giftProductIds)
                    ->values()
                    ->all();
                $inCartProductIds = $giftLines
                    ->filter(fn (mixed $line): bool => (int) data_get($line, 'free_gift_id') === (int) $freeGift->getKey())
                    ->pluck('product_id')
                    ->map(fn (mixed $id): int => (int) $id)
                    ->intersect($giftProductIds)
                    ->values()
                    ->all();

                $freeGift->setAttribute('selected_product_ids', $selectedProductIds);
                $freeGift->setAttribute('declined_product_ids', $declinedProductIds);
                $freeGift->setAttribute('in_cart_product_ids', $inCartProductIds);

                return $freeGift;
            })
            ->values();
    }

    private function resolvePurchasedLines(Model $cart): Collection
    {
        if ($cart->relationLoaded('lines')) {
            $lines = $cart->getRelation('lines');

            if ($lines instanceof Collection) {
                return $lines
                    ->filter(fn (mixed $line): bool => !(bool) data_get($line, 'is_free_gift'))
                    ->values();
            }
        }

        if (!$cart->exists) {
            return collect();
        }

        return query('cart_line')
            ->where('cart_id', $cart->getKey())
            ->where('is_free_gift', false)
            ->get();
    }

    private function resolveGiftLines(Model $cart): Collection
    {
        if ($cart->relationLoaded('lines')) {
            $lines = $cart->getRelation('lines');

            if ($lines instanceof Collection) {
                return $lines
                    ->filter(fn (mixed $line): bool => (bool) data_get($line, 'is_free_gift'))
                    ->values();
            }
        }

        if (!$cart->exists) {
            return collect();
        }

        return query('cart_line')
            ->where('cart_id', $cart->getKey())
            ->where('is_free_gift', true)
            ->get();
    }

    private function resolveDecisionsByFreeGiftId(Model $cart): Collection
    {
        if (!$cart->exists) {
            return collect();
        }

        $decisions = query('cart_free_gift_decision')
            ->where('cart_id', $cart->getKey())
            ->get();

        return $decisions->groupBy(fn (Model $decision): int => (int) $decision->getAttribute('free_gift_id'));
    }

    private function matchesUser(FreeGift $freeGift, ?int $userId): bool
    {
        $qualifyingUserIds = $freeGift->qualifyingUsers
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        if ($qualifyingUserIds->isEmpty()) {
            return true;
        }

        if ($userId === null) {
            return false;
        }

        return $qualifyingUserIds->contains($userId);
    }

    private function matchesSubtotal(FreeGift $freeGift, float $subtotal): bool
    {
        $minimum = $freeGift->getAttribute('minimum_cart_subtotal');
        $maximum = $freeGift->getAttribute('maximum_cart_subtotal');

        if ($minimum !== null && $subtotal < (float) $minimum) {
            return false;
        }

        if ($maximum !== null && $subtotal > (float) $maximum) {
            return false;
        }

        return true;
    }

    private function matchesQuantity(FreeGift $freeGift, int $quantity): bool
    {
        $minimum = $freeGift->getAttribute('minimum_cart_quantity');
        $maximum = $freeGift->getAttribute('maximum_cart_quantity');

        if ($minimum !== null && $quantity < (int) $minimum) {
            return false;
        }

        if ($maximum !== null && $quantity > (int) $maximum) {
            return false;
        }

        return true;
    }

    private function matchesProducts(FreeGift $freeGift, Collection $purchasedProductIds): bool
    {
        $qualifyingProductIds = $freeGift->qualifyingProducts
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        if ($qualifyingProductIds->isEmpty()) {
            return true;
        }

        $productMatchMode = $this->enumValue($freeGift->product_match_mode);

        if ($productMatchMode === 'all') {
            return $qualifyingProductIds
                ->diff($purchasedProductIds)
                ->isEmpty();
        }

        return $qualifyingProductIds
            ->intersect($purchasedProductIds)
            ->isNotEmpty();
    }

    private function decisionValue(mixed $value): mixed
    {
        return $this->enumValue($value);
    }

    private function enumValue(mixed $value): mixed
    {
        return is_object($value) && isset($value->value)
            ? $value->value
            : $value;
    }
}
