<?php

namespace PictaStudio\Venditio\Validations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use PictaStudio\Venditio\Validations\Concerns\InteractsWithTranslatableRules;
use PictaStudio\Venditio\Validations\Contracts\ProductValidationRules;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class ProductValidation implements ProductValidationRules
{
    use InteractsWithTranslatableRules;

    public function getStoreValidationRules(): array
    {
        return [
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists($this->tableFor('product'), 'id'),
            ],
            'brand_id' => [
                'nullable',
                'integer',
                Rule::exists($this->tableFor('brand'), 'id'),
            ],
            'product_type_id' => [
                'nullable',
                'integer',
                Rule::exists($this->tableFor('product_type'), 'id'),
            ],
            'tax_class_id' => [
                'nullable',
                'integer',
                Rule::exists($this->tableFor('tax_class'), 'id'),
            ],
            'name' => ['sometimes', 'filled', 'string', 'max:255'],
            'slug' => ['sometimes', 'filled', 'string', 'max:255'],
            'status' => [
                'required',
                'string',
                Rule::enum(config('venditio.product.status_enum')),
            ],
            'active' => ['sometimes', 'boolean'],
            'new' => ['sometimes', 'boolean'],
            'in_evidence' => ['sometimes', 'boolean'],
            'sku' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique($this->tableFor('product'), 'sku'),
            ],
            'ean' => ['nullable', 'string', 'max:255'],
            'visible_from' => ['nullable', 'date'],
            'visible_until' => ['nullable', 'date', 'after_or_equal:visible_from'],
            'description' => ['nullable', 'string'],
            'description_short' => ['nullable', 'string'],
            'images' => ['nullable', 'array'],
            'images.*.alt' => ['required_with:images', 'string', 'max:255'],
            'images.*.src' => ['required_with:images', 'string', 'max:2048'],
            'files' => ['nullable', 'array'],
            'files.*.name' => ['required_with:files', 'string', 'max:255'],
            'files.*.src' => ['required_with:files', 'string', 'max:2048'],
            'measuring_unit' => [
                'nullable',
                'string',
                Rule::enum(config('venditio.product.measuring_unit_enum')),
            ],
            'qty_for_unit' => ['nullable', 'integer', 'min:0'],
            'length' => ['nullable', 'numeric', 'min:0'],
            'width' => ['nullable', 'numeric', 'min:0'],
            'height' => ['nullable', 'numeric', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'metadata' => ['nullable', 'array'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => [
                'integer',
                Rule::exists($this->tableFor('product_category'), 'id'),
            ],
            'inventory' => ['sometimes', 'array'],
            'inventory.currency_id' => ['nullable', 'integer', Rule::exists($this->tableFor('currency'), 'id')],
            'inventory.stock' => ['sometimes', 'integer', 'min:0'],
            'inventory.stock_reserved' => ['sometimes', 'integer', 'min:0'],
            'inventory.stock_min' => ['nullable', 'integer', 'min:0'],
            'inventory.price' => ['sometimes', 'numeric', 'min:0'],
            'inventory.price_includes_tax' => ['sometimes', 'boolean'],
            'inventory.purchase_price' => ['nullable', 'numeric', 'min:0'],
            ...$this->translatableLocaleRules([
                'name' => ['sometimes', 'filled', 'string', 'max:255'],
                'slug' => ['sometimes', 'filled', 'string', 'max:255'],
                'description' => ['sometimes', 'nullable', 'string'],
                'description_short' => ['sometimes', 'nullable', 'string'],
            ]),
        ];
    }

    public function getUpdateValidationRules(): array
    {
        return [
            'parent_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists($this->tableFor('product'), 'id'),
            ],
            'brand_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists($this->tableFor('brand'), 'id'),
            ],
            'product_type_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists($this->tableFor('product_type'), 'id'),
            ],
            'tax_class_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists($this->tableFor('tax_class'), 'id'),
            ],
            'name' => ['sometimes', 'filled', 'string', 'max:255'],
            'slug' => ['sometimes', 'filled', 'string', 'max:255'],
            'status' => [
                'sometimes',
                'string',
                Rule::enum(config('venditio.product.status_enum')),
            ],
            'active' => ['sometimes', 'boolean'],
            'new' => ['sometimes', 'boolean'],
            'in_evidence' => ['sometimes', 'boolean'],
            'sku' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique($this->tableFor('product'), 'sku')->ignore($this->productId()),
            ],
            'ean' => ['nullable', 'string', 'max:255'],
            'visible_from' => ['nullable', 'date'],
            'visible_until' => ['nullable', 'date', 'after_or_equal:visible_from'],
            'description' => ['nullable', 'string'],
            'description_short' => ['nullable', 'string'],
            'images' => ['nullable', 'array'],
            'images.*.alt' => ['required_with:images', 'string', 'max:255'],
            'images.*.src' => ['required_with:images', 'string', 'max:2048'],
            'files' => ['nullable', 'array'],
            'files.*.name' => ['required_with:files', 'string', 'max:255'],
            'files.*.src' => ['required_with:files', 'string', 'max:2048'],
            'measuring_unit' => [
                'nullable',
                'string',
                Rule::enum(config('venditio.product.measuring_unit_enum')),
            ],
            'qty_for_unit' => ['nullable', 'integer', 'min:0'],
            'length' => ['nullable', 'numeric', 'min:0'],
            'width' => ['nullable', 'numeric', 'min:0'],
            'height' => ['nullable', 'numeric', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'metadata' => ['nullable', 'array'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => [
                'integer',
                Rule::exists($this->tableFor('product_category'), 'id'),
            ],
            'inventory' => ['sometimes', 'array'],
            'inventory.currency_id' => ['nullable', 'integer', Rule::exists($this->tableFor('currency'), 'id')],
            'inventory.stock' => ['sometimes', 'integer', 'min:0'],
            'inventory.stock_reserved' => ['sometimes', 'integer', 'min:0'],
            'inventory.stock_min' => ['nullable', 'integer', 'min:0'],
            'inventory.price' => ['sometimes', 'numeric', 'min:0'],
            'inventory.price_includes_tax' => ['sometimes', 'boolean'],
            'inventory.purchase_price' => ['nullable', 'numeric', 'min:0'],
            ...$this->translatableLocaleRules([
                'name' => ['sometimes', 'filled', 'string', 'max:255'],
                'slug' => ['sometimes', 'filled', 'string', 'max:255'],
                'description' => ['sometimes', 'nullable', 'string'],
                'description_short' => ['sometimes', 'nullable', 'string'],
            ]),
        ];
    }

    private function tableFor(string $model): string
    {
        return (new (resolve_model($model)))->getTable();
    }

    private function productId(): ?int
    {
        $product = request()?->route('product');

        if ($product instanceof Model) {
            return $product->getKey();
        }

        return is_numeric($product) ? (int) $product : null;
    }
}
