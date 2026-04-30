<?php

namespace PictaStudio\Venditio\Http\Requests\V1\Concerns;

trait NormalizesMetadataInput
{
    protected function normalizeMetadataInput(): void
    {
        if (!$this->has('metadata') || !is_array($this->input('metadata'))) {
            return;
        }

        $this->merge([
            'metadata' => $this->nullEmptyStrings($this->input('metadata')),
        ]);
    }

    private function nullEmptyStrings(array $values): array
    {
        return array_map(function (mixed $value): mixed {
            if (is_array($value)) {
                return $this->nullEmptyStrings($value);
            }

            return $value === '' ? null : $value;
        }, $values);
    }
}
