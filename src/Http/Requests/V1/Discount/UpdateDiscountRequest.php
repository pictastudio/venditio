<?php

namespace PictaStudio\Venditio\Http\Requests\V1\Discount;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;
use PictaStudio\Venditio\Enums\DiscountType;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class UpdateDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $discountId = $this->route('discount')?->getKey();

        return [
            'discountable_type' => [
                'nullable',
                'string',
                'max:255',
                Rule::in(['product', 'product_category', 'product_collection', 'product_type', 'brand', 'user']),
            ],
            'discountable_id' => [
                'nullable',
                'integer',
                'required_with:discountable_type',
                Rule::exists($this->tableFor($this->discountable_type), 'id'),
            ],
            'type' => ['sometimes', Rule::enum(DiscountType::class)],
            'value' => ['sometimes', 'numeric', 'min:0'],
            'name' => ['nullable', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:50', Rule::unique($this->discountsTable(), 'code')->ignore($discountId)],
            'active' => ['sometimes', 'boolean'],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'uses' => ['sometimes', 'integer', 'min:0'],
            'max_uses' => ['nullable', 'integer', 'min:0'],
            'apply_to_cart_total' => ['sometimes', 'boolean'],
            'apply_once_per_cart' => ['sometimes', 'boolean'],
            'max_uses_per_user' => ['nullable', 'integer', 'min:1'],
            'one_per_user' => ['sometimes', 'boolean'],
            'free_shipping' => ['sometimes', 'boolean'],
            'first_purchase_only' => ['sometimes', 'boolean'],
            'minimum_order_total' => ['nullable', 'numeric', 'min:0'],
            'priority' => ['sometimes', 'integer'],
            'stop_after_propagation' => ['sometimes', 'boolean'],
        ];
    }

    public function prepareForValidation(): void
    {
        if (array_key_exists('starts_at', $this->all()) && $this->input('starts_at') === null) {
            $this->merge([
                'starts_at' => Date::now(),
            ]);
        }
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
}
