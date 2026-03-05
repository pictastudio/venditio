<?php

namespace PictaStudio\Venditio\Http\Requests\V1\PriceListPrice;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use PictaStudio\Venditio\Validations\Contracts\PriceListPriceValidationRules;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class UpsertMultiplePriceListPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(PriceListPriceValidationRules $priceListPriceValidationRules): array
    {
        if (method_exists($priceListPriceValidationRules, 'getBulkUpsertValidationRules')) {
            return $priceListPriceValidationRules->getBulkUpsertValidationRules();
        }

        return $this->fallbackValidationRules();
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $prices = $this->input('prices', []);
            $seenTuples = [];
            $defaultByProduct = [];

            foreach ($prices as $index => $pricePayload) {
                if (!array_key_exists('product_id', $pricePayload) || !array_key_exists('price_list_id', $pricePayload)) {
                    continue;
                }

                $productId = (int) $pricePayload['product_id'];
                $priceListId = (int) $pricePayload['price_list_id'];
                $tupleKey = $this->tupleKey($productId, $priceListId);

                if (array_key_exists($tupleKey, $seenTuples)) {
                    $validator->errors()->add(
                        "prices.{$index}.price_list_id",
                        'Duplicate product_id and price_list_id combination in bulk payload.'
                    );
                }

                $seenTuples[$tupleKey] = true;

                $isDefault = filter_var(
                    $pricePayload['is_default'] ?? false,
                    FILTER_VALIDATE_BOOLEAN
                ) === true;

                if (!$isDefault) {
                    continue;
                }

                if (array_key_exists($productId, $defaultByProduct)) {
                    $validator->errors()->add(
                        "prices.{$index}.is_default",
                        'Only one price can be marked as default per product in a bulk request.'
                    );
                }

                $defaultByProduct[$productId] = true;
            }
        });
    }

    private function fallbackValidationRules(): array
    {
        return [
            'prices' => ['required', 'array', 'min:1'],
            'prices.*.product_id' => ['required', 'integer', Rule::exists($this->tableFor('product'), 'id')],
            'prices.*.price_list_id' => ['required', 'integer', Rule::exists($this->tableFor('price_list'), 'id')],
            'prices.*.price' => ['required', 'numeric', 'min:0'],
            'prices.*.purchase_price' => ['nullable', 'numeric', 'min:0'],
            'prices.*.price_includes_tax' => ['sometimes', 'boolean'],
            'prices.*.is_default' => ['sometimes', 'boolean'],
            'prices.*.metadata' => ['nullable', 'array'],
        ];
    }

    private function tableFor(string $model): string
    {
        return (new (resolve_model($model)))->getTable();
    }

    private function tupleKey(int $productId, int $priceListId): string
    {
        return $productId . ':' . $priceListId;
    }
}
