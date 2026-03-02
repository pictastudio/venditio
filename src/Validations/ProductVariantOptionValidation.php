<?php

namespace PictaStudio\Venditio\Validations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use PictaStudio\Venditio\Validations\Concerns\InteractsWithTranslatableRules;
use PictaStudio\Venditio\Validations\Contracts\ProductVariantOptionValidationRules;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class ProductVariantOptionValidation implements ProductVariantOptionValidationRules
{
    use InteractsWithTranslatableRules;

    public function getStoreValidationRules(): array
    {
        return [
            'product_variant_id' => [
                'required',
                'integer',
                Rule::exists($this->tableFor('product_variant'), 'id'),
            ],
            'name' => [
                'sometimes',
                'filled',
                'string',
                'max:255',
                $this->uniqueNameWithinProductVariantRule(),
            ],
            'image' => ['sometimes', 'nullable', 'string'],
            'hex_color' => ['sometimes', 'nullable', 'string', 'max:20'],
            'sort_order' => ['required', 'integer', 'min:0'],
            ...$this->translatableLocaleRules([
                'name' => ['sometimes', 'filled', 'string', 'max:255'],
            ]),
        ];
    }

    public function getUpdateValidationRules(): array
    {
        return [
            'product_variant_id' => [
                'sometimes',
                'integer',
                Rule::exists($this->tableFor('product_variant'), 'id'),
                $this->uniqueProductVariantWithinNameRule($this->routeModelKey('product_variant_option')),
            ],
            'name' => [
                'sometimes',
                'filled',
                'string',
                'max:255',
                $this->uniqueNameWithinProductVariantRule($this->routeModelKey('product_variant_option')),
            ],
            'image' => ['sometimes', 'nullable', 'string'],
            'hex_color' => ['sometimes', 'nullable', 'string', 'max:20'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            ...$this->translatableLocaleRules([
                'name' => ['sometimes', 'filled', 'string', 'max:255'],
            ]),
        ];
    }

    private function tableFor(string $model): string
    {
        return (new (resolve_model($model)))->getTable();
    }

    private function uniqueNameWithinProductVariantRule(?int $ignoreProductVariantOptionId = null): Unique
    {
        $variantId = $this->resolveProductVariantId();

        $rule = Rule::unique($this->tableFor('product_variant_option'), 'name')
            ->where(function ($query) use ($variantId): void {
                if ($variantId === null) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                $query->where('product_variant_id', $variantId);
            });

        if ($ignoreProductVariantOptionId !== null) {
            $rule = $rule->ignore($ignoreProductVariantOptionId);
        }

        return $rule;
    }

    private function resolveProductVariantId(): ?int
    {
        $inputVariantId = request()?->input('product_variant_id');

        if (is_numeric($inputVariantId)) {
            return (int) $inputVariantId;
        }

        $productVariantOption = request()?->route('product_variant_option');

        if ($productVariantOption instanceof Model && is_numeric($productVariantOption->product_variant_id)) {
            return (int) $productVariantOption->product_variant_id;
        }

        return null;
    }

    private function uniqueProductVariantWithinNameRule(?int $ignoreProductVariantOptionId = null): Unique
    {
        $name = $this->resolveOptionName();

        $rule = Rule::unique($this->tableFor('product_variant_option'), 'product_variant_id')
            ->where(function ($query) use ($name): void {
                if ($name === null) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                $query->where('name', $name);
            });

        if ($ignoreProductVariantOptionId !== null) {
            $rule = $rule->ignore($ignoreProductVariantOptionId);
        }

        return $rule;
    }

    private function resolveOptionName(): ?string
    {
        $inputName = request()?->input('name');

        if (is_string($inputName) && filled($inputName)) {
            return $inputName;
        }

        $productVariantOption = request()?->route('product_variant_option');

        if ($productVariantOption instanceof Model && is_string($productVariantOption->name) && filled($productVariantOption->name)) {
            return $productVariantOption->name;
        }

        return null;
    }
}
