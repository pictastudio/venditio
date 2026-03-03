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
        ];
    }

    protected function applyProductIndexRelationFilters(Builder $query, array $filters): void
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
    }
}
