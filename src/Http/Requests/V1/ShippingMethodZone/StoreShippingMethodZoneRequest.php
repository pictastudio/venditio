<?php

namespace PictaStudio\Venditio\Http\Requests\V1\ShippingMethodZone;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class StoreShippingMethodZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shipping_method_id' => ['required', 'integer', Rule::exists($this->tableFor('shipping_method'), 'id')],
            'shipping_zone_id' => ['required', 'integer', Rule::exists($this->tableFor('shipping_zone'), 'id')],
            'active' => ['sometimes', 'boolean'],
            'rate_tiers' => ['nullable', 'array'],
            'rate_tiers.*.max_weight' => ['required_with:rate_tiers', 'numeric', 'gt:0'],
            'rate_tiers.*.fee' => ['required_with:rate_tiers', 'numeric', 'min:0'],
            'over_weight_price_per_kg' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->ensurePricingExists($validator);
            $this->ensureRateTiersAreSorted($validator, $this->input('rate_tiers', []));

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (!$this->shippingMethodZoneExists(
                (int) $this->input('shipping_method_id'),
                (int) $this->input('shipping_zone_id')
            )) {
                return;
            }

            $validator->errors()->add(
                'shipping_zone_id',
                'The shipping_method_id and shipping_zone_id combination has already been taken.'
            );
        });
    }

    private function ensurePricingExists($validator): void
    {
        $rateTiers = $this->input('rate_tiers', []);
        $overWeight = $this->input('over_weight_price_per_kg');

        if (filled($overWeight) || (is_array($rateTiers) && $rateTiers !== [])) {
            return;
        }

        $validator->errors()->add(
            'rate_tiers',
            'At least one rate tier or an over_weight_price_per_kg value is required.'
        );
    }

    private function ensureRateTiersAreSorted($validator, mixed $rateTiers): void
    {
        if (!is_array($rateTiers)) {
            return;
        }

        $previousMaxWeight = null;

        foreach ($rateTiers as $index => $rateTier) {
            $currentMaxWeight = is_array($rateTier) ? (float) ($rateTier['max_weight'] ?? 0) : 0;

            if ($previousMaxWeight !== null && $currentMaxWeight <= $previousMaxWeight) {
                $validator->errors()->add(
                    "rate_tiers.{$index}.max_weight",
                    'Rate tiers must be sorted by max_weight in ascending order.'
                );
            }

            $previousMaxWeight = $currentMaxWeight;
        }
    }

    private function shippingMethodZoneExists(int $shippingMethodId, int $shippingZoneId): bool
    {
        return resolve_model('shipping_method_zone')::query()
            ->where('shipping_method_id', $shippingMethodId)
            ->where('shipping_zone_id', $shippingZoneId)
            ->exists();
    }

    private function tableFor(string $model): string
    {
        return (new (resolve_model($model)))->getTable();
    }
}
