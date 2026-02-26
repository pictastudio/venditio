<?php

namespace PictaStudio\Venditio\Models\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait ResolvesRouteBindingByIdOrSlug
{
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        if ($field !== null) {
            return parent::resolveRouteBinding($value, $field);
        }

        $model = $this->resolveRouteBindingQuery(
            $this->newQuery(),
            $value,
            $this->getKeyName()
        )->first();

        if ($model !== null) {
            return $model;
        }

        $model = $this->resolveRouteBindingQuery(
            $this->newQuery(),
            $value,
            'slug'
        )->first();

        if ($model !== null) {
            return $model;
        }

        return $this->resolveByTranslatedSlug($value);
    }

    private function resolveByTranslatedSlug(mixed $value): ?Model
    {
        if (
            !method_exists($this, 'getTranslationModelName')
            || !method_exists($this, 'getLocaleKey')
            || !method_exists($this, 'getMorphClass')
        ) {
            return null;
        }

        $translationModel = $this->getTranslationModelName();
        if (!is_string($translationModel) || !is_a($translationModel, Model::class, true)) {
            return null;
        }

        $localeColumn = $this->getLocaleKey();
        if (!is_string($localeColumn) || $localeColumn === '') {
            $localeColumn = 'locale';
        }

        $translatedId = $translationModel::query()
            ->where('translatable_type', $this->getMorphClass())
            ->where('attribute', 'slug')
            ->where('value', (string) $value)
            ->when(
                app()->getLocale(),
                fn (Builder $query, string $locale) => $query->where($localeColumn, $locale)
            )
            ->value('translatable_id');

        if ($translatedId === null) {
            $translatedId = $translationModel::query()
                ->where('translatable_type', $this->getMorphClass())
                ->where('attribute', 'slug')
                ->where('value', (string) $value)
                ->value('translatable_id');
        }

        if ($translatedId === null) {
            return null;
        }

        return $this->resolveRouteBindingQuery(
            $this->newQuery(),
            $translatedId,
            $this->getKeyName()
        )->first();
    }
}
