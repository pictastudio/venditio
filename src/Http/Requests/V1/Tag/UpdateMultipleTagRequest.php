<?php

namespace PictaStudio\Venditio\Http\Requests\V1\Tag;

use Illuminate\Foundation\Http\FormRequest;
use PictaStudio\Venditio\Validations\Contracts\TagValidationRules;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class UpdateMultipleTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(TagValidationRules $tagValidationRules): array
    {
        return $tagValidationRules->getBulkUpdateValidationRules();
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $tags = $this->input('tags', []);
            $incomingParents = [];
            $indexById = [];

            foreach ($tags as $index => $tag) {
                if (
                    !array_key_exists('parent_id', $tag)
                    || !array_key_exists('id', $tag)
                ) {
                    continue;
                }

                $id = (int) $tag['id'];
                $parentId = $tag['parent_id'] === null
                    ? null
                    : (int) $tag['parent_id'];

                $incomingParents[$id] = $parentId;
                $indexById[$id] = $index;

                if ($parentId === null) {
                    continue;
                }

                if ((int) $tag['parent_id'] === (int) $tag['id']) {
                    $validator->errors()->add(
                        "tags.{$index}.parent_id",
                        'The parent_id field must be different from id.'
                    );
                }
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $currentParents = resolve_model('tag')::query()
                ->get()
                ->mapWithKeys(
                    fn ($tag): array => [
                        (int) $tag->getKey() => $tag->parent_id === null
                            ? null
                            : (int) $tag->parent_id,
                    ]
                )
                ->all();

            foreach (array_keys($incomingParents) as $tagId) {
                if (!$this->createsCircularReference($tagId, $incomingParents, $currentParents)) {
                    continue;
                }

                $validator->errors()->add(
                    'tags.' . $indexById[$tagId] . '.parent_id',
                    'The parent_id field creates a circular reference.'
                );
            }
        });
    }

    private function createsCircularReference(int $startId, array $incomingParents, array $currentParents): bool
    {
        $visited = [];
        $cursor = $startId;

        while ($cursor !== null) {
            if (isset($visited[$cursor])) {
                return true;
            }

            $visited[$cursor] = true;
            $cursor = array_key_exists($cursor, $incomingParents)
                ? $incomingParents[$cursor]
                : ($currentParents[$cursor] ?? null);
        }

        return false;
    }
}
