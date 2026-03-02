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

class OrdersByLineExport implements FromCollection, WithColumnFormatting, WithHeadings, WithMapping
{
    private const DATE_COLUMNS = [
        'order_last_tracked_at',
        'order_approved_at',
    ];

    private const DECIMAL_COLUMNS = [
        'order_sub_total_taxable',
        'order_sub_total_tax',
        'order_sub_total',
        'order_shipping_fee',
        'order_payment_fee',
        'order_discount_amount',
        'order_total_final',
        'line_discount_amount',
        'line_unit_price',
        'line_purchase_price',
        'line_unit_discount',
        'line_unit_final_price',
        'line_unit_final_price_tax',
        'line_unit_final_price_taxable',
        'line_total_final_price',
        'line_tax_rate',
    ];

    private const INTEGER_COLUMNS = [
        'line_qty',
    ];

    /**
     * @param  Collection<int, Model>  $orders
     * @param  array<int, string>  $columns
     */
    public function __construct(
        private readonly Collection $orders,
        private readonly array $columns,
    ) {}

    public function collection(): Collection
    {
        return $this->orders
            ->flatMap(function (Model $order): Collection {
                $lines = $order->relationLoaded('lines')
                    ? $order->lines
                    : collect();

                return $lines->map(fn (Model $line): array => [
                    'order' => $order,
                    'line' => $line,
                ]);
            })
            ->values();
    }

    public function map($row): array
    {
        /** @var Model $order */
        $order = $row['order'];
        /** @var Model $line */
        $line = $row['line'];

        return collect($this->columns)
            ->map(fn (string $column) => $this->resolveColumnValue($order, $line, $column))
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

    private function resolveColumnValue(Model $order, Model $line, string $column): mixed
    {
        $value = match ($column) {
            'order_identifier' => $order->identifier,
            'order_status' => $order->status,
            'order_user_id' => $this->relationDisplayValue($order->user, ['email', 'name', 'first_name', 'last_name']),
            'order_shipping_status_id' => $this->relationDisplayValue($order->shippingStatus, ['external_code', 'name']),
            'order_tracking_code' => $order->tracking_code,
            'order_tracking_link' => $order->tracking_link,
            'order_last_tracked_at' => $order->last_tracked_at,
            'order_courier_code' => $order->courier_code,
            'order_sub_total_taxable' => $order->sub_total_taxable,
            'order_sub_total_tax' => $order->sub_total_tax,
            'order_sub_total' => $order->sub_total,
            'order_shipping_fee' => $order->shipping_fee,
            'order_payment_fee' => $order->payment_fee,
            'order_discount_code' => $order->discount_code,
            'order_discount_amount' => $order->discount_amount,
            'order_total_final' => $order->total_final,
            'order_user_first_name' => $order->user_first_name,
            'order_user_last_name' => $order->user_last_name,
            'order_user_email' => $order->user_email,
            'order_customer_notes' => $order->customer_notes,
            'order_admin_notes' => $order->admin_notes,
            'order_approved_at' => $order->approved_at,
            'line_product_id' => $this->productDataValue($line, ['sku', 'name', 'slug', 'id'])
                ?? $line->product_sku
                ?? $line->product_name,
            'line_currency_id' => $this->relationDisplayValue($line->currency, ['code', 'name']),
            'line_product_name' => $this->productDataValue($line, ['name']) ?? $line->product_name,
            'line_product_sku' => $this->productDataValue($line, ['sku']) ?? $line->product_sku,
            'line_discount_code' => $line->discount_code,
            'line_discount_amount' => $line->discount_amount,
            'line_unit_price' => $line->unit_price,
            'line_purchase_price' => $line->purchase_price,
            'line_unit_discount' => $line->unit_discount,
            'line_unit_final_price' => $line->unit_final_price,
            'line_unit_final_price_tax' => $line->unit_final_price_tax,
            'line_unit_final_price_taxable' => $line->unit_final_price_taxable,
            'line_qty' => $line->qty,
            'line_total_final_price' => $line->total_final_price,
            'line_tax_rate' => $line->tax_rate,
            default => null,
        };

        return $this->normalizeValue($value, $column);
    }

    private function productDataValue(Model $line, array $paths): mixed
    {
        $productData = $line->getAttribute('product_data');

        if (!is_array($productData)) {
            return null;
        }

        foreach ($paths as $path) {
            $value = data_get($productData, $path);

            if (filled($value)) {
                return $value;
            }
        }

        return null;
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

        if (in_array($column, self::INTEGER_COLUMNS, true)) {
            return NumberFormat::FORMAT_NUMBER;
        }

        return null;
    }
}
