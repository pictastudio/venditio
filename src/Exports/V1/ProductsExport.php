<?php

namespace PictaStudio\Venditio\Exports\V1;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\{FromCollection, WithColumnFormatting, WithHeadings, WithMapping};
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use UnitEnum;

class ProductsExport implements FromCollection, WithColumnFormatting, WithHeadings, WithMapping
{
    private const DATE_COLUMNS = [
        'visible_from',
        'visible_until',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    private const DECIMAL_COLUMNS = [
        'length',
        'width',
        'height',
        'weight',
        'price',
        'purchase_price',
    ];

    private const INTEGER_COLUMNS = [
        'id',
        'qty_for_unit',
        'stock',
        'stock_reserved',
        'stock_available',
        'stock_min',
        'minimum_reorder_quantity',
        'reorder_lead_days',
    ];

    private const BOOLEAN_COLUMNS = [
        'active',
        'new',
        'in_evidence',
        'price_includes_tax',
    ];

    /**
     * @param  Collection<int, Model>  $products
     * @param  array<int, string>  $columns
     */
    public function __construct(
        private readonly Collection $products,
        private readonly array $columns,
    ) {}

    public function collection(): Collection
    {
        return $this->products;
    }

    public function map($row): array
    {
        return collect($this->columns)
            ->map(fn (string $column) => $this->resolveColumnValue($row, $column))
            ->all();
    }

    public function headings(): array
    {
        return $this->columns;
    }

    public function columnFormats(): array
    {
        return collect($this->columns)
            ->mapWithKeys(function (string $column, int $index): array {
                $format = $this->resolveColumnFormat($column);

                if ($format === null) {
                    return [];
                }

                return [
                    Coordinate::stringFromColumnIndex($index + 1) => $format,
                ];
            })
            ->all();
    }

    private function resolveColumnValue(Model $product, string $column): mixed
    {
        $value = match ($column) {
            'id' => $product->getKey(),
            'parent_id' => $this->relationDisplayValue($product->parent, ['sku', 'name', 'slug']),
            'brand_id' => $this->relationDisplayValue($product->brand, ['name', 'slug']),
            'product_type_id' => $this->relationDisplayValue($product->productType, ['name', 'slug']),
            'tax_class_id' => $this->relationDisplayValue($product->taxClass, ['name']),
            'price' => $product->inventory?->price,
            'purchase_price' => $product->inventory?->purchase_price,
            'price_includes_tax' => $product->inventory?->price_includes_tax,
            'currency_id' => $this->relationDisplayValue($product->inventory?->currency, ['code', 'name']),
            'stock' => $product->inventory?->stock,
            'stock_reserved' => $product->inventory?->stock_reserved,
            'stock_available' => $product->inventory?->stock_available,
            'stock_min' => $product->inventory?->stock_min,
            'minimum_reorder_quantity' => $product->inventory?->minimum_reorder_quantity,
            'reorder_lead_days' => $product->inventory?->reorder_lead_days,
            'category_ids' => $product->relationLoaded('categories')
                ? $product->categories
                    ->map(fn (Model $category): mixed => $this->relationDisplayValue($category, ['name', 'slug']))
                    ->filter(fn (mixed $value) => filled($value))
                    ->implode(',')
                : null,
            'collection_ids' => $product->relationLoaded('collections')
                ? $product->collections
                    ->map(fn (Model $collection): mixed => $this->relationDisplayValue($collection, ['name', 'slug']))
                    ->filter(fn (mixed $value) => filled($value))
                    ->implode(',')
                : null,
            'variant_option_ids' => $product->relationLoaded('variantOptions')
                ? $product->variantOptions
                    ->map(fn (Model $option): mixed => $this->relationDisplayValue($option, ['name']))
                    ->filter(fn (mixed $value) => filled($value))
                    ->implode(',')
                : null,
            default => $product->{$column},
        };

        return $this->normalizeValue($value, $column);
    }

    private function relationDisplayValue(?Model $relation, array $candidateAttributes): mixed
    {
        if (!$relation) {
            return null;
        }

        foreach ($candidateAttributes as $attribute) {
            $value = $relation->{$attribute} ?? null;

            if (filled($value)) {
                return $value;
            }
        }

        return $relation->getKey();
    }

    private function normalizeValue(mixed $value, string $column): mixed
    {
        if ($value instanceof UnitEnum) {
            return $value instanceof BackedEnum ? $value->value : $value->name;
        }

        if ($value instanceof DateTimeInterface) {
            return in_array($column, self::DATE_COLUMNS, true)
                ? ExcelDate::dateTimeToExcel($value)
                : $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return (int) $value;
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        if (in_array($column, self::DECIMAL_COLUMNS, true) && is_numeric($value)) {
            return (float) $value;
        }

        if (in_array($column, self::INTEGER_COLUMNS, true) && is_numeric($value)) {
            return (int) $value;
        }

        if (in_array($column, self::BOOLEAN_COLUMNS, true) && $value !== null) {
            return (int) filter_var($value, FILTER_VALIDATE_BOOL);
        }

        return $value;
    }

    private function resolveColumnFormat(string $column): ?string
    {
        if (in_array($column, self::DATE_COLUMNS, true)) {
            return NumberFormat::FORMAT_DATE_DATETIME;
        }

        if (in_array($column, self::DECIMAL_COLUMNS, true)) {
            return NumberFormat::FORMAT_NUMBER_00;
        }

        if (in_array($column, self::INTEGER_COLUMNS, true) || in_array($column, self::BOOLEAN_COLUMNS, true)) {
            return NumberFormat::FORMAT_NUMBER;
        }

        return null;
    }
}
