<?php

namespace PictaStudio\Venditio\Pipelines\Cart\Pipes;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use PictaStudio\Venditio\FreeGifts\FreeGiftEligibilityResolver;
use PictaStudio\Venditio\Models\FreeGift;
use PictaStudio\Venditio\Pipelines\CartLine\CartLineUpdatePipeline;

use function PictaStudio\Venditio\Helpers\Functions\{query, resolve_dto};

class SyncFreeGifts
{
    public function __construct(
        private readonly FreeGiftEligibilityResolver $freeGiftEligibilityResolver,
    ) {}

    public function __invoke(Model $cart, Closure $next): Model
    {
        $regularLines = $cart->getRelation('lines');

        if (!$regularLines instanceof Collection) {
            $regularLines = collect($regularLines ?? []);
        }

        $regularLines = $regularLines
            ->filter(fn (mixed $line): bool => !(bool) data_get($line, 'is_free_gift'))
            ->values();
        $existingGiftLinesByKey = $this->loadExistingGiftLinesByKey($cart);
        $eligibleFreeGifts = $this->freeGiftEligibilityResolver->resolveForCart(
            $cart,
            $regularLines,
            $existingGiftLinesByKey->flatMap(fn (Collection $lines) => $lines)
        );
        $giftLines = collect();

        foreach ($eligibleFreeGifts as $freeGift) {
            foreach ($this->desiredGiftProductIds($freeGift) as $productId) {
                $lineKey = $this->giftLineKey((int) $freeGift->getKey(), $productId);
                $existingLinesForKey = $existingGiftLinesByKey->get($lineKey, collect());
                $cartLine = $existingLinesForKey->shift() ?? resolve_dto('cart_line')::getFreshInstance();
                $existingGiftLinesByKey->put($lineKey, $existingLinesForKey);
                $cartLine->setRelation('cart', $cart);
                $cartLine->cart()->associate($cart);

                $giftLine = CartLineUpdatePipeline::make()->run(
                    resolve_dto('cart_line')::fromArray([
                        'cart_line' => $cartLine,
                        'product_id' => $productId,
                        'qty' => 1,
                    ])
                );

                $giftLine->cart()->associate($cart);
                $giftLine->setRelation('cart', $cart);
                $giftLines->push($this->markAsFreeGift($giftLine, $freeGift));
            }
        }

        $linesToDelete = collect($cart->getAttribute('lines_to_delete') ?? [])
            ->merge(
                $existingGiftLinesByKey
                    ->flatMap(fn (Collection $lines) => $lines)
                    ->pluck('id')
            )
            ->filter()
            ->unique()
            ->values()
            ->all();

        $cart->setAttribute('lines_to_delete', $linesToDelete);
        $cart->setRelation('lines', $regularLines->concat($giftLines)->values());

        return $next($cart);
    }

    private function loadExistingGiftLinesByKey(Model $cart): Collection
    {
        if (!$cart->exists) {
            return collect();
        }

        return query('cart_line')
            ->where('cart_id', $cart->getKey())
            ->where('is_free_gift', true)
            ->get()
            ->groupBy(fn (Model $line): string => $this->giftLineKey(
                (int) $line->getAttribute('free_gift_id'),
                (int) $line->getAttribute('product_id'),
            ))
            ->map(fn (Collection $lines): Collection => $lines->values());
    }

    private function desiredGiftProductIds(FreeGift $freeGift): array
    {
        $giftProductIds = $freeGift->giftProducts
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();
        $selectedProductIds = collect($freeGift->getAttribute('selected_product_ids') ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->intersect($giftProductIds)
            ->values();
        $declinedProductIds = collect($freeGift->getAttribute('declined_product_ids') ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->intersect($giftProductIds)
            ->values();

        if ($this->enumValue($freeGift->mode) === 'manual') {
            if ($this->enumValue($freeGift->selection_mode) === 'single') {
                return $selectedProductIds
                    ->take(1)
                    ->values()
                    ->all();
            }

            return $selectedProductIds->all();
        }

        if ((bool) $freeGift->allow_decline) {
            return $giftProductIds
                ->reject(fn (int $productId): bool => $declinedProductIds->contains($productId))
                ->values()
                ->all();
        }

        return $giftProductIds->all();
    }

    private function markAsFreeGift(Model $line, FreeGift $freeGift): Model
    {
        $productData = $line->getAttribute('product_data');
        $productData = is_array($productData) ? $productData : [];

        data_set($productData, 'price_calculated.price', 0.0);
        data_set($productData, 'price_calculated.price_final', 0.0);
        data_set($productData, 'price_calculated.discounts_applied', []);
        data_set($productData, 'price_calculated.free_gift', $this->freeGiftSnapshot($freeGift));

        $line->fill([
            'free_gift_id' => $freeGift->getKey(),
            'is_free_gift' => true,
            'free_gift_data' => $this->freeGiftSnapshot($freeGift),
            'discount_id' => null,
            'discount_code' => null,
            'discount_amount' => 0,
            'unit_price' => 0,
            'unit_discount' => 0,
            'unit_final_price' => 0,
            'unit_final_price_tax' => 0,
            'unit_final_price_taxable' => 0,
            'total_final_price' => 0,
            'qty' => 1,
            'product_data' => $productData,
        ]);

        return $line;
    }

    private function freeGiftSnapshot(FreeGift $freeGift): array
    {
        return [
            'id' => (int) $freeGift->getKey(),
            'name' => $freeGift->name,
            'mode' => $this->enumValue($freeGift->mode),
            'selection_mode' => $this->enumValue($freeGift->selection_mode),
            'allow_decline' => (bool) $freeGift->allow_decline,
            'product_match_mode' => $this->enumValue($freeGift->product_match_mode),
        ];
    }

    private function giftLineKey(int $freeGiftId, int $productId): string
    {
        return implode(':', [$freeGiftId, $productId]);
    }

    private function enumValue(mixed $value): mixed
    {
        return is_object($value) && isset($value->value)
            ? $value->value
            : $value;
    }
}
