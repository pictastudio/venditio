<?php

namespace PictaStudio\Venditio\Http\Requests\V1\Discount;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use PictaStudio\Venditio\Enums\DiscountType;
use PictaStudio\Venditio\Models\Discount;

use function PictaStudio\Venditio\Helpers\Functions\{query, resolve_model};

class UpsertMultipleDiscountRequest extends FormRequest
{
    private ?Collection $existingDiscounts = null;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'discounts' => ['required', 'array', 'min:1'],
            'discounts.*' => ['array'],
        ];

        foreach ($this->input('discounts', []) as $index => $discountPayload) {
            $discountPayload = is_array($discountPayload) ? $discountPayload : [];
            $discountId = array_key_exists('id', $discountPayload) && filled($discountPayload['id'])
                ? (int) $discountPayload['id']
                : null;

            $rules["discounts.{$index}.id"] = ['sometimes', 'integer', Rule::exists($this->discountsTable(), 'id')];
            $rules["discounts.{$index}.discountable_type"] = [
                'nullable',
                'string',
                'max:255',
                Rule::in($this->discountableTypes()),
            ];
            $rules["discounts.{$index}.discountable_id"] = [
                'nullable',
                'integer',
                Rule::exists($this->tableFor($discountPayload['discountable_type'] ?? null), 'id'),
            ];
            $rules["discounts.{$index}.type"] = ['sometimes', Rule::enum(DiscountType::class)];
            $rules["discounts.{$index}.value"] = ['sometimes', 'numeric', 'min:0'];
            $rules["discounts.{$index}.name"] = ['nullable', 'string', 'max:255'];
            $rules["discounts.{$index}.code"] = [
                'nullable',
                'string',
                'max:50',
                Rule::unique($this->discountsTable(), 'code')->ignore($discountId),
            ];
            $rules["discounts.{$index}.active"] = ['sometimes', 'boolean'];
            $rules["discounts.{$index}.starts_at"] = ['sometimes', 'date'];
            $rules["discounts.{$index}.ends_at"] = ['nullable', 'date'];
            $rules["discounts.{$index}.uses"] = ['sometimes', 'integer', 'min:0'];
            $rules["discounts.{$index}.max_uses"] = ['nullable', 'integer', 'min:0'];
            $rules["discounts.{$index}.apply_to_cart_total"] = ['sometimes', 'boolean'];
            $rules["discounts.{$index}.apply_once_per_cart"] = ['sometimes', 'boolean'];
            $rules["discounts.{$index}.max_uses_per_user"] = ['nullable', 'integer', 'min:1'];
            $rules["discounts.{$index}.one_per_user"] = ['sometimes', 'boolean'];
            $rules["discounts.{$index}.free_shipping"] = ['sometimes', 'boolean'];
            $rules["discounts.{$index}.minimum_order_total"] = ['nullable', 'numeric', 'min:0'];
            $rules["discounts.{$index}.priority"] = ['sometimes', 'integer'];
            $rules["discounts.{$index}.stop_after_propagation"] = ['sometimes', 'boolean'];
        }

        return $rules;
    }

    public function prepareForValidation(): void
    {
        $payload = $this->all();
        $jsonPayload = $this->json()->all();

        if (is_array($jsonPayload) && array_is_list($jsonPayload)) {
            $payload = [
                ...$payload,
                'discounts' => $jsonPayload,
            ];
        }

        $discounts = $payload['discounts'] ?? [];
        $existingDiscounts = $this->resolveExistingDiscounts($discounts);
        $reservedCodes = collect($discounts)
            ->map(fn (mixed $discount): ?string => is_array($discount) ? ($discount['code'] ?? null) : null)
            ->filter(fn (?string $code): bool => filled($code))
            ->mapWithKeys(fn (string $code): array => [mb_strtolower($code) => true])
            ->all();

        $payload['discounts'] = collect($discounts)
            ->map(function (mixed $discountPayload) use (&$reservedCodes, $existingDiscounts): mixed {
                if (!is_array($discountPayload)) {
                    return $discountPayload;
                }

                $discountId = array_key_exists('id', $discountPayload) && filled($discountPayload['id'])
                    ? (int) $discountPayload['id']
                    : null;
                $existingDiscount = $discountId !== null
                    ? $existingDiscounts->get($discountId)
                    : null;

                foreach (['starts_at', 'ends_at'] as $attribute) {
                    if (!array_key_exists($attribute, $discountPayload) || blank($discountPayload[$attribute])) {
                        continue;
                    }

                    $discountPayload[$attribute] = Date::parse($discountPayload[$attribute]);
                }

                if (filled($discountPayload['code'] ?? null)) {
                    $reservedCodes[mb_strtolower((string) $discountPayload['code'])] = true;

                    return $discountPayload;
                }

                if ($existingDiscount instanceof Discount && filled($existingDiscount->code)) {
                    $discountPayload['code'] = $existingDiscount->code;
                    $reservedCodes[mb_strtolower($existingDiscount->code)] = true;

                    return $discountPayload;
                }

                if (!$this->hasDiscountableTarget($discountPayload, $existingDiscount)) {
                    return $discountPayload;
                }

                $discountPayload['code'] = $this->generateAutomaticCode($reservedCodes);
                $reservedCodes[mb_strtolower($discountPayload['code'])] = true;

                return $discountPayload;
            })
            ->all();

        $this->replace($payload);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $discounts = $this->input('discounts', []);
            $existingDiscounts = $this->existingDiscounts();
            $seenIds = [];
            $seenCodes = [];

            foreach ($discounts as $index => $discountPayload) {
                if (!is_array($discountPayload)) {
                    continue;
                }

                $discountId = array_key_exists('id', $discountPayload) && filled($discountPayload['id'])
                    ? (int) $discountPayload['id']
                    : null;
                $existingDiscount = $discountId !== null
                    ? $existingDiscounts->get($discountId)
                    : null;

                if ($discountId !== null) {
                    if (array_key_exists($discountId, $seenIds)) {
                        $validator->errors()->add(
                            "discounts.{$index}.id",
                            'Duplicate discount id in bulk payload.'
                        );
                    }

                    $seenIds[$discountId] = true;
                }

                if ($discountId === null) {
                    foreach (['type', 'value', 'starts_at'] as $requiredField) {
                        if (array_key_exists($requiredField, $discountPayload) && filled($discountPayload[$requiredField])) {
                            continue;
                        }

                        $validator->errors()->add(
                            "discounts.{$index}.{$requiredField}",
                            "The {$requiredField} field is required when creating a discount via bulk upsert."
                        );
                    }
                }

                $hasDiscountableType = array_key_exists('discountable_type', $discountPayload)
                    && filled($discountPayload['discountable_type']);
                $hasDiscountableId = array_key_exists('discountable_id', $discountPayload)
                    && filled($discountPayload['discountable_id']);

                if ($hasDiscountableType xor $hasDiscountableId) {
                    $missingField = $hasDiscountableType ? 'discountable_id' : 'discountable_type';

                    $validator->errors()->add(
                        "discounts.{$index}.{$missingField}",
                        "The {$missingField} field is required with the related discountable field."
                    );
                }

                $effectiveCode = $discountPayload['code'] ?? $existingDiscount?->code;

                if (!$this->hasDiscountableTarget($discountPayload, $existingDiscount) && blank($effectiveCode)) {
                    $validator->errors()->add(
                        "discounts.{$index}.code",
                        'The code field is required when the discount is not scoped to a discountable resource.'
                    );
                }

                if (filled($effectiveCode)) {
                    $normalizedCode = mb_strtolower((string) $effectiveCode);

                    if (array_key_exists($normalizedCode, $seenCodes)) {
                        $validator->errors()->add(
                            "discounts.{$index}.code",
                            'Duplicate discount code in bulk payload.'
                        );
                    }

                    $seenCodes[$normalizedCode] = true;
                }

                $startsAt = $discountPayload['starts_at'] ?? $existingDiscount?->starts_at;
                $endsAt = $discountPayload['ends_at'] ?? null;

                if (filled($startsAt) && filled($endsAt) && Date::parse($endsAt)->lt(Date::parse($startsAt))) {
                    $validator->errors()->add(
                        "discounts.{$index}.ends_at",
                        'The ends_at field must be a date after or equal to starts_at.'
                    );
                }
            }
        });
    }

    private function tableFor(?string $model): string
    {
        $resolvedModel = filled($model) ? $model : 'product';

        return (new (resolve_model($resolvedModel)))->getTable();
    }

    private function discountsTable(): string
    {
        return (new (resolve_model('discount')))->getTable();
    }

    private function discountableTypes(): array
    {
        return ['product', 'product_category', 'product_collection', 'product_type', 'brand', 'user'];
    }

    private function existingDiscounts(): Collection
    {
        if ($this->existingDiscounts instanceof Collection) {
            return $this->existingDiscounts;
        }

        $this->existingDiscounts = $this->resolveExistingDiscounts($this->input('discounts', []));

        return $this->existingDiscounts;
    }

    private function resolveExistingDiscounts(array $discounts): Collection
    {
        $discountIds = collect($discounts)
            ->map(fn (mixed $discount): mixed => is_array($discount) ? ($discount['id'] ?? null) : null)
            ->filter(fn (mixed $discountId): bool => filled($discountId))
            ->map(fn (mixed $discountId): int => (int) $discountId)
            ->unique()
            ->values()
            ->all();

        if ($discountIds === []) {
            return new Collection;
        }

        return query('discount')
            ->withTrashed()
            ->whereKey($discountIds)
            ->get()
            ->keyBy(fn (Discount $discount): int => (int) $discount->getKey());
    }

    private function hasDiscountableTarget(array $discountPayload, ?Discount $existingDiscount = null): bool
    {
        if (
            array_key_exists('discountable_type', $discountPayload)
            || array_key_exists('discountable_id', $discountPayload)
        ) {
            return filled($discountPayload['discountable_type'] ?? null)
                && filled($discountPayload['discountable_id'] ?? null);
        }

        return $existingDiscount instanceof Discount
            && filled($existingDiscount->discountable_type)
            && filled($existingDiscount->discountable_id);
    }

    private function generateAutomaticCode(array $reservedCodes): string
    {
        do {
            $code = 'AUTO-' . mb_strtoupper(Str::random(12));
        } while (
            array_key_exists(mb_strtolower($code), $reservedCodes)
            || query('discount')->where('code', $code)->exists()
        );

        return $code;
    }
}
