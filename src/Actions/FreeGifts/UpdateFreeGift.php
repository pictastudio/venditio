<?php

namespace PictaStudio\Venditio\Actions\FreeGifts;

use Illuminate\Support\Arr;
use PictaStudio\Venditio\Models\FreeGift;

class UpdateFreeGift
{
    public function handle(FreeGift $freeGift, array $payload): FreeGift
    {
        $qualifyingUsersProvided = array_key_exists('qualifying_user_ids', $payload);
        $qualifyingProductsProvided = array_key_exists('qualifying_product_ids', $payload);
        $giftProductsProvided = array_key_exists('gift_product_ids', $payload);

        $qualifyingUserIds = Arr::pull($payload, 'qualifying_user_ids', []);
        $qualifyingProductIds = Arr::pull($payload, 'qualifying_product_ids', []);
        $giftProductIds = Arr::pull($payload, 'gift_product_ids', []);

        $freeGift->fill($payload);
        $freeGift->save();

        if ($qualifyingUsersProvided) {
            $freeGift->qualifyingUsers()->sync($this->normalizeIds($qualifyingUserIds));
        }

        if ($qualifyingProductsProvided) {
            $freeGift->qualifyingProducts()->sync($this->normalizeIds($qualifyingProductIds));
        }

        if ($giftProductsProvided) {
            $freeGift->giftProducts()->sync($this->normalizeIds($giftProductIds));
        }

        return $freeGift->refresh()->load($this->relations());
    }

    private function normalizeIds(array $ids): array
    {
        return collect($ids)
            ->filter(fn (mixed $id): bool => filled($id))
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function relations(): array
    {
        return [
            'qualifyingUsers',
            'qualifyingProducts',
            'giftProducts',
        ];
    }
}
