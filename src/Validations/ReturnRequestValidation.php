<?php

namespace PictaStudio\Venditio\Validations;

use Illuminate\Validation\Rule;
use PictaStudio\Venditio\Validations\Contracts\ReturnRequestValidationRules;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class ReturnRequestValidation implements ReturnRequestValidationRules
{
    public function getStoreValidationRules(): array
    {
        return [
            'order_id' => ['required', 'integer', Rule::exists($this->tableFor('order'), 'id')],
            'return_reason_id' => $this->returnReasonRules(required: true),
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'is_accepted' => ['sometimes', 'boolean'],
            'is_verified' => ['sometimes', 'boolean'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.order_line_id' => ['required_with:lines', 'integer', 'distinct', Rule::exists($this->tableFor('order_line'), 'id')],
            'lines.*.qty' => ['required_with:lines.*.order_line_id', 'integer', 'min:1'],
        ];
    }

    public function getUpdateValidationRules(): array
    {
        return [
            'return_reason_id' => $this->returnReasonRules(required: false),
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'is_accepted' => ['sometimes', 'boolean'],
            'is_verified' => ['sometimes', 'boolean'],
            'lines' => ['sometimes', 'array', 'min:1'],
            'lines.*.order_line_id' => ['required_with:lines', 'integer', 'distinct', Rule::exists($this->tableFor('order_line'), 'id')],
            'lines.*.qty' => ['required_with:lines.*.order_line_id', 'integer', 'min:1'],
        ];
    }

    private function returnReasonRules(bool $required): array
    {
        return [
            $required ? 'required' : 'sometimes',
            'integer',
            Rule::exists($this->tableFor('return_reason'), 'id')
                ->where(fn ($query) => $query->where('active', true)->whereNull('deleted_at')),
        ];
    }

    private function tableFor(string $model): string
    {
        return (new (resolve_model($model)))->getTable();
    }
}
