<?php

namespace PictaStudio\Venditio\Validations;

use Illuminate\Validation\Rule;
use PictaStudio\Venditio\Validations\Contracts\ReturnReasonValidationRules;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class ReturnReasonValidation implements ReturnReasonValidationRules
{
    public function getStoreValidationRules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:100',
                Rule::unique($this->tableFor('return_reason'), 'code')->withoutTrashed(),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function getUpdateValidationRules(): array
    {
        $returnReason = request()->route('return_reason');
        $returnReasonId = $returnReason?->getKey();

        return [
            'code' => [
                'sometimes',
                'string',
                'max:100',
                Rule::unique($this->tableFor('return_reason'), 'code')
                    ->withoutTrashed()
                    ->ignore($returnReasonId),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    private function tableFor(string $model): string
    {
        return (new (resolve_model($model)))->getTable();
    }
}
