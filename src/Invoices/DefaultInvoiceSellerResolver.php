<?php

namespace PictaStudio\Venditio\Invoices;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\{DB, Schema};
use PictaStudio\Venditio\Contracts\InvoiceSellerResolverInterface;
use Throwable;

class DefaultInvoiceSellerResolver implements InvoiceSellerResolverInterface
{
    /**
     * @return array<string, mixed>
     */
    public function resolve(): array
    {
        return [
            ...$this->configuredSeller(),
            ...$this->settingsSeller(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function configuredSeller(): array
    {
        return $this->filledValues(config('venditio.invoices.seller', []));
    }

    /**
     * @return array<string, mixed>
     */
    protected function settingsSeller(): array
    {
        if (!config('venditio.invoices.seller_settings.enabled', true)) {
            return [];
        }

        $table = $this->settingsTable();
        $groupColumn = $this->settingsColumn('group', 'group');
        $nameColumn = $this->settingsColumn('name', 'name');
        $valueColumn = $this->settingsColumn('value', 'value');

        if (
            blank($table)
            || !Schema::hasTable($table)
            || !Schema::hasColumn($table, $groupColumn)
            || !Schema::hasColumn($table, $nameColumn)
            || !Schema::hasColumn($table, $valueColumn)
        ) {
            return [];
        }

        $fieldMap = $this->fieldMap();

        if ($fieldMap === []) {
            return [];
        }

        try {
            $settings = DB::table($table)
                ->where($groupColumn, $this->settingsGroup())
                ->whereIn($nameColumn, array_values($fieldMap))
                ->pluck($valueColumn, $nameColumn);
        } catch (Throwable) {
            return [];
        }

        return $this->filledValues(
            collect($fieldMap)
                ->mapWithKeys(fn (string $settingName, string $sellerKey): array => [
                    $sellerKey => $settings->get($settingName),
                ])
                ->all()
        );
    }

    protected function settingsTable(): string
    {
        $configuredTable = config('venditio.invoices.seller_settings.table');

        if (is_string($configuredTable) && filled($configuredTable)) {
            return $configuredTable;
        }

        $contentoTable = config('contento.table_names.settings');

        if (is_string($contentoTable) && filled($contentoTable)) {
            return $contentoTable;
        }

        return 'settings';
    }

    protected function settingsGroup(): string
    {
        $group = config('venditio.invoices.seller_settings.group', 'company');

        return is_string($group) && filled($group) ? $group : 'company';
    }

    protected function settingsColumn(string $key, string $default): string
    {
        $columns = config('venditio.invoices.seller_settings.columns', []);
        $column = is_array($columns) ? Arr::get($columns, $key, $default) : $default;

        return is_string($column) && filled($column) ? $column : $default;
    }

    /**
     * @return array<string, string>
     */
    protected function fieldMap(): array
    {
        $fieldMap = config('venditio.invoices.seller_settings.field_map', []);

        if (!is_array($fieldMap)) {
            return [];
        }

        return collect($fieldMap)
            ->filter(fn (mixed $settingName, mixed $sellerKey): bool => (
                is_string($sellerKey)
                && filled($sellerKey)
                && is_string($settingName)
                && filled($settingName)
            ))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function filledValues(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        return collect($values)
            ->filter(fn (mixed $value): bool => filled($value))
            ->all();
    }
}
