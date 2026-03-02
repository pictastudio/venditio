<?php

namespace PictaStudio\Venditio\Http\Requests\V1\Export;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'columns' => [
                'sometimes',
                'array',
                'min:1',
            ],
            'columns.*' => [
                'string',
                Rule::in(config('venditio.exports.orders.allowed_columns', [])),
            ],
            'filename' => [
                'sometimes',
                'string',
                'max:120',
                'regex:/^[A-Za-z0-9._-]+$/',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $columns = $this->input('columns');

        if (!is_string($columns)) {
            return;
        }

        $this->merge([
            'columns' => collect(explode(',', $columns))
                ->map(fn (string $column) => mb_trim($column))
                ->filter(fn (string $column) => filled($column))
                ->values()
                ->all(),
        ]);
    }
}
