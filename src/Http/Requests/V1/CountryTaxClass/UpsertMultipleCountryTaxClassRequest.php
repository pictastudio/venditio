<?php

namespace PictaStudio\Venditio\Http\Requests\V1\CountryTaxClass;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class UpsertMultipleCountryTaxClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'country_tax_classes' => ['required', 'array', 'min:1'],
            'country_tax_classes.*.country_id' => ['required', 'integer', Rule::exists($this->tableFor('country'), 'id')],
            'country_tax_classes.*.tax_class_id' => ['required', 'integer', Rule::exists($this->tableFor('tax_class'), 'id')],
            'country_tax_classes.*.rate' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $countryTaxClasses = $this->validationData()['country_tax_classes'] ?? [];
            $seenTuples = [];

            foreach ($countryTaxClasses as $index => $countryTaxClass) {
                if (
                    !array_key_exists('country_id', $countryTaxClass)
                    || !array_key_exists('tax_class_id', $countryTaxClass)
                ) {
                    continue;
                }

                $tupleKey = $this->tupleKey(
                    (int) $countryTaxClass['country_id'],
                    (int) $countryTaxClass['tax_class_id']
                );

                if (array_key_exists($tupleKey, $seenTuples)) {
                    $validator->errors()->add(
                        "country_tax_classes.{$index}.tax_class_id",
                        'Duplicate country_id and tax_class_id combination in bulk payload.'
                    );
                }

                $seenTuples[$tupleKey] = true;
            }
        });
    }

    public function validationData(): array
    {
        $jsonPayload = $this->json()->all();

        if (is_array($jsonPayload) && array_is_list($jsonPayload)) {
            return [
                'country_tax_classes' => $jsonPayload,
            ];
        }

        return parent::validationData();
    }

    private function tableFor(string $model): string
    {
        return (new (resolve_model($model)))->getTable();
    }

    private function tupleKey(int $countryId, int $taxClassId): string
    {
        return $countryId . ':' . $taxClassId;
    }
}
