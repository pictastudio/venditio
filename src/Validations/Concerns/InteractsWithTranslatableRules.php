<?php

namespace PictaStudio\Venditio\Validations\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use PictaStudio\Translatable\{Locales, Translation};

trait InteractsWithTranslatableRules
{
    protected function translatableLocaleRules(array $attributesRules): array
    {
        $rules = [];

        foreach ($this->translatableLocales() as $locale) {
            $rules[$locale] = ['sometimes', 'array'];

            foreach ($attributesRules as $attribute => $attributeRules) {
                $rules[$locale . '.' . $attribute] = $attributeRules;
                $rules[$attribute . ':' . $locale] = $attributeRules;
            }
        }

        return $rules;
    }

    /**
     * @param  array<int, mixed>|Closure(string):array<int, mixed>  $attributeRules
     */
    protected function translatableLocaleRulesForAttribute(
        string $attribute,
        array|Closure $attributeRules
    ): array {
        $rules = [];

        foreach ($this->translatableLocales() as $locale) {
            $rules[$locale] = ['sometimes', 'array'];

            $localeRules = $attributeRules instanceof Closure
                ? $attributeRules($locale)
                : $attributeRules;

            $rules[$locale . '.' . $attribute] = $localeRules;
            $rules[$attribute . ':' . $locale] = $localeRules;
        }

        return $rules;
    }

    protected function uniqueTranslatedAttributeValueRule(
        string $translatableType,
        string $locale,
        string $attribute,
        ?int $ignoreTranslatableId = null
    ): Unique {
        $rule = Rule::unique($this->translationTable(), 'value')
            ->where(function (Builder $query) use ($translatableType, $locale, $attribute): void {
                $query->where('translatable_type', $translatableType)
                    ->where($this->translationLocaleColumn(), $locale)
                    ->where('attribute', $attribute);
            });

        if ($ignoreTranslatableId !== null) {
            $rule = $rule->ignore($ignoreTranslatableId, 'translatable_id');
        }

        return $rule;
    }

    protected function routeModelKey(string $parameter): ?int
    {
        $routeValue = request()?->route($parameter);

        if ($routeValue instanceof Model) {
            $routeKey = $routeValue->getKey();

            return is_numeric($routeKey) ? (int) $routeKey : null;
        }

        return is_numeric($routeValue) ? (int) $routeValue : null;
    }

    protected function translatableLocales(): array
    {
        $locales = app(Locales::class)->all();

        if ($locales === []) {
            return [app()->getLocale()];
        }

        return collect($locales)
            ->filter(fn (mixed $locale): bool => is_string($locale) && filled($locale))
            ->values()
            ->all();
    }

    private function translationTable(): string
    {
        $translationModel = config('translatable.translation_model', Translation::class);

        if (!is_string($translationModel) || !is_a($translationModel, Model::class, true)) {
            $translationModel = Translation::class;
        }

        /** @var class-string<Model> $translationModel */
        return (new $translationModel)->getTable();
    }

    private function translationLocaleColumn(): string
    {
        $localeColumn = config('translatable.locale_key', 'locale');

        return is_string($localeColumn) && $localeColumn !== '' ? $localeColumn : 'locale';
    }
}
