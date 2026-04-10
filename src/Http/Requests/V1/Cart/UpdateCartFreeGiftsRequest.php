<?php

namespace PictaStudio\Venditio\Http\Requests\V1\Cart;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class UpdateCartFreeGiftsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'free_gifts' => ['required', 'array', 'min:1'],
            'free_gifts.*' => ['array'],
            'free_gifts.*.free_gift_id' => ['required', 'integer', Rule::exists($this->tableFor('free_gift'), 'id')],
            'free_gifts.*.selected_product_ids' => ['sometimes', 'array'],
            'free_gifts.*.selected_product_ids.*' => ['integer', 'distinct', Rule::exists($this->tableFor('product'), 'id')],
            'free_gifts.*.declined_product_ids' => ['sometimes', 'array'],
            'free_gifts.*.declined_product_ids.*' => ['integer', 'distinct', Rule::exists($this->tableFor('product'), 'id')],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $seenFreeGiftIds = [];

            foreach ($this->input('free_gifts', []) as $index => $payload) {
                if (!is_array($payload)) {
                    continue;
                }

                $freeGiftId = (int) ($payload['free_gift_id'] ?? 0);

                if (array_key_exists($freeGiftId, $seenFreeGiftIds)) {
                    $validator->errors()->add(
                        "free_gifts.{$index}.free_gift_id",
                        'Duplicate free gift id in payload.'
                    );
                }

                $seenFreeGiftIds[$freeGiftId] = true;

                $selectedProductIds = collect($payload['selected_product_ids'] ?? [])
                    ->map(fn (mixed $id): int => (int) $id)
                    ->unique();
                $declinedProductIds = collect($payload['declined_product_ids'] ?? [])
                    ->map(fn (mixed $id): int => (int) $id)
                    ->unique();
                $overlappingIds = $selectedProductIds
                    ->intersect($declinedProductIds)
                    ->values()
                    ->all();

                if ($overlappingIds !== []) {
                    $validator->errors()->add(
                        "free_gifts.{$index}.selected_product_ids",
                        'A gift product cannot be both selected and declined.'
                    );
                }
            }
        });
    }

    private function tableFor(string $model): string
    {
        return (new (resolve_model($model)))->getTable();
    }
}
