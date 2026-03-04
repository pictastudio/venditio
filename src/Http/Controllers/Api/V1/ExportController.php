<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use PictaStudio\Venditio\Exports\V1\{OrdersByLineExport, ProductsExport};
use PictaStudio\Venditio\Http\Controllers\Api\Controller;
use PictaStudio\Venditio\Http\Requests\V1\Export\{ExportOrdersRequest, ExportProductsRequest};

use function PictaStudio\Venditio\Helpers\Functions\{query, resolve_model};

class ExportController extends Controller
{
    public function products(ExportProductsRequest $request)
    {
        $this->authorizeIfConfigured('viewAny', resolve_model('product'));

        $columns = $this->resolveColumns(
            $request->validated('columns'),
            config('venditio.exports.products.default_columns', [])
        );

        $filters = $this->resolveFilters($request->all());
        $query = query('product')->with([
            'parent',
            'brand',
            'productType',
            'taxClass',
            'inventory.currency',
            'categories',
            'variantOptions',
        ]);
        $this->applyProductIndexRelationFilters($query, $filters);

        if ($this->shouldExcludeVariants($filters)) {
            $query->whereNull('parent_id');
        }

        $products = $this->applyBaseFilters(
            $query,
            $filters,
            'product',
            $this->productIndexValidationRules()
        );

        return Excel::download(
            new ProductsExport(collect($products), $columns),
            $this->resolveFilename($request->validated('filename'), 'products'),
            ExcelFormat::XLSX
        );
    }

    public function orders(ExportOrdersRequest $request)
    {
        $this->authorizeIfConfigured('viewAny', resolve_model('order'));

        $columns = $this->resolveColumns(
            $request->validated('columns'),
            config('venditio.exports.orders.default_columns', [])
        );

        $filters = $this->resolveFilters($request->all());

        $orders = $this->applyBaseFilters(
            query('order')->with([
                'user',
                'shippingStatus',
                'lines.currency',
                'lines.discount',
            ]),
            $filters,
            'order'
        );

        return Excel::download(
            new OrdersByLineExport(collect($orders), $columns),
            $this->resolveFilename($request->validated('filename'), 'orders'),
            ExcelFormat::XLSX
        );
    }

    protected function resolveColumns(?array $columns, array $defaults): array
    {
        if (blank($columns)) {
            return $defaults;
        }

        return collect($columns)
            ->map(fn (string $column) => mb_trim($column))
            ->filter(fn (string $column) => filled($column))
            ->unique()
            ->values()
            ->all();
    }

    protected function resolveFilters(array $payload): array
    {
        return collect($payload)
            ->except(['columns', 'filename'])
            ->merge(['all' => true])
            ->all();
    }

    protected function resolveFilename(?string $filename, string $prefix): string
    {
        $filename = filled($filename)
            ? mb_trim($filename)
            : $prefix . '-' . now()->format('Ymd_His');

        if (!str($filename)->lower()->endsWith('.xlsx')) {
            $filename .= '.xlsx';
        }

        return $filename;
    }

    protected function shouldExcludeVariants(array $filters): bool
    {
        $excludeVariants = (bool) config('venditio.product.exclude_variants_from_index', true);

        if (array_key_exists('include_variants', $filters)) {
            $excludeVariants = !filter_var($filters['include_variants'], FILTER_VALIDATE_BOOL);
        }

        if (array_key_exists('exclude_variants', $filters)) {
            $excludeVariants = filter_var($filters['exclude_variants'], FILTER_VALIDATE_BOOL);
        }

        return $excludeVariants;
    }

    protected function productIndexValidationRules(): array
    {
        $brandModel = app(resolve_model('brand'));
        $brandTable = method_exists($brandModel, 'getTableName')
            ? $brandModel->getTableName()
            : $brandModel->getTable();
        $brandKeyName = $brandModel->getKeyName();

        $categoryModel = app(resolve_model('product_category'));
        $categoryTable = method_exists($categoryModel, 'getTableName')
            ? $categoryModel->getTableName()
            : $categoryModel->getTable();
        $categoryKeyName = $categoryModel->getKeyName();

        return [
            'include_variants' => [
                'sometimes',
                'boolean',
            ],
            'exclude_variants' => [
                'sometimes',
                'boolean',
            ],
            'brand_ids' => [
                'sometimes',
                'array',
                'min:1',
            ],
            'brand_ids.*' => [
                'integer',
                Rule::exists($brandTable, $brandKeyName),
            ],
            'category_ids' => [
                'sometimes',
                'array',
                'min:1',
            ],
            'category_ids.*' => [
                'integer',
                Rule::exists($categoryTable, $categoryKeyName),
            ],
            'price' => [
                'sometimes',
                'numeric',
            ],
            'price_operator' => [
                'sometimes',
                'string',
                Rule::in(['>', '<', '>=', '<=', '=']),
            ],
        ];
    }

    protected function applyProductIndexRelationFilters(Builder $query, array &$filters): void
    {
        if (isset($filters['brand_ids']) && is_array($filters['brand_ids'])) {
            $query->whereIn('brand_id', $filters['brand_ids']);
        }

        if (isset($filters['category_ids']) && is_array($filters['category_ids'])) {
            $productModel = app(resolve_model('product'));
            $productTable = method_exists($productModel, 'getTableName')
                ? $productModel->getTableName()
                : $productModel->getTable();
            $productKey = $productModel->getKeyName();

            $categoriesRelation = $productModel->categories();
            $pivotTable = $categoriesRelation->getTable();
            $foreignPivotKey = $categoriesRelation->getForeignPivotKeyName();
            $relatedPivotKey = $categoriesRelation->getRelatedPivotKeyName();

            $query->whereIn(
                $productTable . '.' . $productKey,
                fn ($subQuery) => $subQuery
                    ->select($foreignPivotKey)
                    ->from($pivotTable)
                    ->whereIn($relatedPivotKey, $filters['category_ids'])
            );
        }

        if (array_key_exists('price', $filters)) {
            $priceOperator = $this->sanitizePriceOperator(
                is_string($filters['price_operator'] ?? null)
                    ? $filters['price_operator']
                    : '='
            );
            $price = (float) $filters['price'];

            $query->whereHas(
                'inventory',
                fn (Builder $inventoryQuery) => $inventoryQuery->where('price', $priceOperator, $price)
            );
        }

        if (($filters['sort_by'] ?? null) === 'price') {
            $this->applyPriceSort($query, $filters);

            unset($filters['sort_by']);
        }
    }

    protected function applyPriceSort(Builder $query, array $filters): void
    {
        $productModel = app(resolve_model('product'));
        $productTable = method_exists($productModel, 'getTableName')
            ? $productModel->getTableName()
            : $productModel->getTable();
        $productKeyName = $productModel->getKeyName();

        $inventoryModel = app(resolve_model('inventory'));
        $inventoryTable = method_exists($inventoryModel, 'getTableName')
            ? $inventoryModel->getTableName()
            : $inventoryModel->getTable();

        $sortDirection = $this->sanitizeSortDirection(
            is_string($filters['sort_dir'] ?? null)
                ? $filters['sort_dir']
                : 'asc'
        );

        $query->orderBy(
            $inventoryModel->newQuery()
                ->select($inventoryTable . '.price')
                ->whereColumn(
                    $inventoryTable . '.product_id',
                    $productTable . '.' . $productKeyName
                )
                ->limit(1),
            $sortDirection
        );
    }

    protected function sanitizePriceOperator(string $operator): string
    {
        return in_array($operator, ['>', '<', '>=', '<=', '='], true)
            ? $operator
            : '=';
    }

    protected function sanitizeSortDirection(string $sortDirection): string
    {
        $sortDirection = mb_strtolower($sortDirection);

        return in_array($sortDirection, ['asc', 'desc'], true)
            ? $sortDirection
            : 'asc';
    }
}
