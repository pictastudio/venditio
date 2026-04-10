<?php

namespace PictaStudio\Venditio\Actions\FreeGifts;

use Illuminate\Support\Arr;
use PictaStudio\Venditio\Models\FreeGift;

use function PictaStudio\Venditio\Helpers\Functions\query;

class CreateFreeGift
{
    public function handle(array $payload): FreeGift
    {
        $qualifyingUserIds = Arr::pull($payload, 'qualifying_user_ids', []);
        $qualifyingProductIds = Arr::pull($payload, 'qualifying_product_ids', []);
        $giftProductIds = Arr::pull($payload, 'gift_product_ids', []);

        /** @var FreeGift $freeGift */
        $freeGift = query('free_gift')->create($payload);

        $freeGift->qualifyingUsers()->sync($this->normalizeIds($qualifyingUserIds));
        $freeGift->qualifyingProducts()->sync($this->normalizeIds($qualifyingProductIds));
        $freeGift->giftProducts()->sync($this->normalizeIds($giftProductIds));

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
