<?php

namespace PictaStudio\Venditio\Http\Requests\V1\ShippingMethodZone;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class UpdateShippingMethodZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shipping_method_id' => ['sometimes', 'integer', Rule::exists($this->tableFor('shipping_method'), 'id')],
            'shipping_zone_id' => ['sometimes', 'integer', Rule::exists($this->tableFor('shipping_zone'), 'id')],
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

            $shippingMethodZone = $this->route('shipping_method_zone');
            $shippingMethodId = (int) ($this->input('shipping_method_id') ?? $shippingMethodZone?->shipping_method_id);
            $shippingZoneId = (int) ($this->input('shipping_zone_id') ?? $shippingMethodZone?->shipping_zone_id);
            $payload = $this->validationData();
            $rateTiers = array_key_exists('rate_tiers', $payload)
                ? $this->input('rate_tiers')
                : $shippingMethodZone?->rate_tiers;
            $overWeight = array_key_exists('over_weight_price_per_kg', $payload)
                ? $this->input('over_weight_price_per_kg')
                : $shippingMethodZone?->over_weight_price_per_kg;

            $this->ensurePricingExists($validator, $rateTiers, $overWeight);
            $this->ensureRateTiersAreSorted($validator, $rateTiers);

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (!$this->shippingMethodZoneExists($shippingMethodId, $shippingZoneId, (int) $shippingMethodZone?->getKey())) {
                return;
            }

            $validator->errors()->add(
                'shipping_zone_id',
                'The shipping_method_id and shipping_zone_id combination has already been taken.'
            );
        });
    }

    private function ensurePricingExists($validator, mixed $rateTiers, mixed $overWeight): void
    {
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

    private function shippingMethodZoneExists(int $shippingMethodId, int $shippingZoneId, int $ignoreId): bool
    {
        return resolve_model('shipping_method_zone')::query()
            ->where('shipping_method_id', $shippingMethodId)
            ->where('shipping_zone_id', $shippingZoneId)
            ->whereKeyNot($ignoreId)
            ->exists();
    }

    private function tableFor(string $model): string
    {
        return (new (resolve_model($model)))->getTable();
    }
}
