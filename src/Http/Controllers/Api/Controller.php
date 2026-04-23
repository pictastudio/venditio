<?php

namespace PictaStudio\Venditio\Http\Controllers\Api;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\{Builder, Collection};
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\{JsonResponse, Response};
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\{Rule, ValidationException};
use PictaStudio\Venditio\Models\Scopes\{Active, InDateRange, ProductStatusActive};
use PictaStudio\Venditio\Traits\ValidatesData;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class Controller extends BaseController
{
    use AuthorizesRequests;
    use DispatchesJobs;
    use ValidatesData;
    use ValidatesRequests;

    public function applyBaseFilters(Builder $query, array $filters, string $model, array $extraValidationRules = []): Collection|LengthAwarePaginator
    {
        $modelInstance = app(resolve_model($model));
        $supportsSoftDeletes = $this->modelSupportsSoftDeletes($modelInstance);
        $table = method_exists($modelInstance, 'getTableName')
            ? $modelInstance->getTableName()
            : $modelInstance->getTable();
        $keyName = $modelInstance->getKeyName();
        $maxPerPage = max(1, (int) config('venditio.routes.api.v1.pagination.max_per_page', 100));

        $filters = collect($filters)
            ->reject(fn (mixed $value) => $value === null || $value === '')
            ->all();

        if (isset($filters['sort_dir']) && is_string($filters['sort_dir'])) {
            $filters['sort_dir'] = mb_strtolower($filters['sort_dir']);
        }

        [
            'rules' => $automaticRules,
            'filterable_columns' => $filterableColumns,
            'sortable_columns' => $sortableColumns,
            'column_types' => $columnTypes,
            'rangeable_columns' => $rangeableColumns,
        ] = $this->buildStaticFilterRules($model, $keyName);

        $rules = array_merge([
            'all' => [
                'sometimes',
                'boolean',
            ],
            'page' => [
                'sometimes',
                'integer',
                'min:1',
            ],
            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:' . $maxPerPage,
            ],
            'sort_by' => [
                'sometimes',
                'string',
                Rule::in($sortableColumns),
            ],
            'sort_dir' => [
                'sometimes',
                'string',
                Rule::in(['asc', 'desc']),
            ],
            'exclude_all_scopes' => [
                'sometimes',
                'boolean',
            ],
            'exclude_active_scope' => [
                'sometimes',
                'boolean',
            ],
            'exclude_date_range_scope' => [
                'sometimes',
                'boolean',
            ],
            'id' => [
                'sometimes',
                'array',
            ],
            'id.*' => [
                'integer',
                Rule::exists(
                    $table,
                    $keyName
                ),
            ],
            ...($supportsSoftDeletes ? [
                'with_trashed' => [
                    'sometimes',
                    'boolean',
                ],
                'only_trashed' => [
                    'sometimes',
                    'boolean',
                ],
            ] : []),
        ], $automaticRules, $extraValidationRules);

        $this->ensureNoUnknownFilterParameters($filters, $rules);
        $validatedFilters = $this->validateData($filters, $rules);

        $this->removeImplicitScopesOverriddenByExplicitFilters($query, $model, $validatedFilters);

        if (isset($validatedFilters['sort_by'])) {
            $query->reorder(
                $validatedFilters['sort_by'],
                $validatedFilters['sort_dir'] ?? 'asc'
            );
        }

        if (isset($validatedFilters['id'])) {
            $query->whereKey($validatedFilters['id']);
        }

        if ($supportsSoftDeletes) {
            $withTrashed = array_key_exists('with_trashed', $validatedFilters)
                ? filter_var($validatedFilters['with_trashed'], FILTER_VALIDATE_BOOL)
                : false;
            $onlyTrashed = array_key_exists('only_trashed', $validatedFilters)
                ? filter_var($validatedFilters['only_trashed'], FILTER_VALIDATE_BOOL)
                : false;

            if ($onlyTrashed) {
                $query->onlyTrashed();
            } elseif ($withTrashed) {
                $query->withTrashed();
            }
        }

        if (in_array('active', $filterableColumns, true) && array_key_exists('is_active', $validatedFilters)) {
            $query->where('active', filter_var($validatedFilters['is_active'], FILTER_VALIDATE_BOOL));
        }

        foreach ($filterableColumns as $column) {
            if (!array_key_exists($column, $validatedFilters)) {
                continue;
            }

            $columnType = $columnTypes[$column] ?? null;
            $value = $validatedFilters[$column];

            if ($this->isStringDeclaredType($columnType)) {
                $query->whereLike($column, $this->prepareStringFilterValue($value), caseSensitive: false);

                continue;
            }

            $query->where(
                $column,
                $this->castFilterValueForDeclaredType($value, $columnType)
            );
        }

        foreach ($rangeableColumns as $column) {
            $startKey = $column . '_start';
            $endKey = $column . '_end';

            if (array_key_exists($startKey, $validatedFilters)) {
                $query->where($column, '>=', $validatedFilters[$startKey]);
            }

            if (array_key_exists($endKey, $validatedFilters)) {
                $query->where($column, '<=', $validatedFilters[$endKey]);
            }
        }

        $all = array_key_exists('all', $validatedFilters)
            ? filter_var($validatedFilters['all'], FILTER_VALIDATE_BOOL)
            : false;

        if ($all) {
            return $query->get();
        }

        return $query->paginate(
            (int) ($validatedFilters['per_page'] ?? config('venditio.routes.api.v1.pagination.per_page'))
        );
    }

    public function successJsonResponse(array|string $data = [], ?string $message = null, int $status = Response::HTTP_OK): JsonResponse
    {
        return $this->jsonResponse($data, $message, $status);
    }

    public function errorJsonResponse(array|string $data = [], ?string $message = null, int $status = Response::HTTP_BAD_REQUEST): JsonResponse
    {
        return $this->jsonResponse($data, $message, $status);
    }

    public function jsonResponse(array|string $data = [], ?string $message = null, int $status = Response::HTTP_OK): JsonResponse
    {
        $response = [
            'status' => true,
            'data' => $data,
        ];

        if ($status !== Response::HTTP_OK) {
            $response['status'] = false;
        }

        if ($message) {
            $response['message'] = $message;
        }

        return response()->json($response, $status);
    }

    protected function resolveIncludes(array $allowedIncludes): array
    {
        $rawIncludes = request()->query('include', []);

        $includes = collect(is_array($rawIncludes) ? $rawIncludes : [$rawIncludes])
            ->flatMap(fn (mixed $include) => is_string($include) ? explode(',', $include) : [])
            ->map(fn (string $include) => mb_trim($include))
            ->filter(fn (string $include) => filled($include))
            ->unique()
            ->values()
            ->all();

        $this->validateData([
            'include' => $includes,
        ], [
            'include' => ['array'],
            'include.*' => [
                'string',
                Rule::in($allowedIncludes),
            ],
        ]);

        return $includes;
    }

    protected function allowedIncludesWithDiscounts(array $allowedIncludes = []): array
    {
        return collect($allowedIncludes)
            ->merge($this->discountIncludeNames())
            ->unique()
            ->values()
            ->all();
    }

    protected function discountRelationsForIncludes(array $includes, ?string $prefix = null): array
    {
        return collect([
            'discounts' => 'discounts',
            'valid_discounts' => 'validDiscounts',
            'expired_discounts' => 'expiredDiscounts',
        ])
            ->filter(fn (string $relation, string $include): bool => in_array($include, $includes, true))
            ->values()
            ->map(fn (string $relation): string => filled($prefix) ? $prefix . '.' . $relation : $relation)
            ->all();
    }

    protected function discountIncludeNames(): array
    {
        return [
            'discounts',
            'valid_discounts',
            'expired_discounts',
        ];
    }

    protected function buildStaticFilterRules(string $model, string $keyName): array
    {
        $columns = $this->queryFilterColumns()[$model] ?? [];
        $rules = [];
        $filterableColumns = array_keys($columns);
        $sortableColumns = [$keyName];
        $columnTypes = $columns;
        $rangeableColumns = [];

        foreach ($columns as $column => $columnType) {
            $sortableColumns[] = $column;
            $rules[$column] = $this->buildRulesForDeclaredType($columnType);

            if ($this->isDateDeclaredType($columnType)) {
                $rangeableColumns[] = $column;
                $rules[$column . '_start'] = ['sometimes', 'date'];
                $rules[$column . '_end'] = ['sometimes', 'date'];
            }
        }

        if (in_array('active', $filterableColumns, true)) {
            $rules['is_active'] = ['sometimes', 'boolean'];
        }

        return [
            'rules' => $rules,
            'filterable_columns' => array_values(array_unique($filterableColumns)),
            'sortable_columns' => array_values(array_unique($sortableColumns)),
            'column_types' => $columnTypes,
            'rangeable_columns' => array_values(array_unique($rangeableColumns)),
        ];
    }

    protected function ensureNoUnknownFilterParameters(array $filters, array $rules): void
    {
        $allowedKeys = collect(array_keys($rules))
            ->map(fn (string $key) => str($key)->before('.')->toString())
            ->map(fn (string $key) => str_ends_with($key, '*') ? mb_substr($key, 0, -1) : $key)
            ->values()
            ->unique()
            ->all();

        $unknownKeys = collect(array_keys($filters))
            ->reject(fn (string $key) => in_array($key, $allowedKeys, true))
            ->values();

        if ($unknownKeys->isEmpty()) {
            return;
        }

        throw ValidationException::withMessages(
            $unknownKeys
                ->mapWithKeys(
                    fn (string $key) => [$key => ['Unsupported query parameter.']]
                )
                ->all()
        );
    }

    protected function castFilterValueForDeclaredType(mixed $value, ?string $columnType): mixed
    {
        if ($columnType === null) {
            return $value;
        }

        if ($this->isBooleanDeclaredType($columnType)) {
            return filter_var($value, FILTER_VALIDATE_BOOL);
        }

        if ($this->isIntegerDeclaredType($columnType)) {
            return (int) $value;
        }

        if ($this->isNumericDeclaredType($columnType)) {
            return (float) $value;
        }

        return $value;
    }

    protected function buildRulesForDeclaredType(string $columnType): array
    {
        if ($this->isBooleanDeclaredType($columnType)) {
            return ['sometimes', 'boolean'];
        }

        if ($this->isIntegerDeclaredType($columnType)) {
            return ['sometimes', 'integer'];
        }

        if ($this->isNumericDeclaredType($columnType)) {
            return ['sometimes', 'numeric'];
        }

        if ($this->isDateDeclaredType($columnType)) {
            return ['sometimes', 'date'];
        }

        return ['sometimes', 'string'];
    }

    protected function prepareStringFilterValue(mixed $value): string
    {
        return '%' . (string) $value . '%';
    }

    protected function isStringDeclaredType(?string $columnType): bool
    {
        return $columnType !== null && mb_strtolower($columnType) === 'string';
    }

    protected function isBooleanDeclaredType(string $columnType): bool
    {
        return in_array(
            mb_strtolower($columnType),
            ['boolean'],
            true
        );
    }

    protected function isIntegerDeclaredType(string $columnType): bool
    {
        return in_array(
            mb_strtolower($columnType),
            ['integer'],
            true
        );
    }

    protected function isNumericDeclaredType(string $columnType): bool
    {
        return in_array(
            mb_strtolower($columnType),
            ['numeric'],
            true
        );
    }

    protected function isDateDeclaredType(string $columnType): bool
    {
        return in_array(
            mb_strtolower($columnType),
            ['date'],
            true
        );
    }

    protected function queryFilterColumns(): array
    {
        return [
            'address' => [
                'addressable_type' => 'string',
                'addressable_id' => 'integer',
                'country_id' => 'integer',
                'province_id' => 'integer',
                'type' => 'string',
                'is_default' => 'boolean',
                'first_name' => 'string',
                'last_name' => 'string',
                'email' => 'string',
                'sex' => 'string',
                'phone' => 'string',
                'vat_number' => 'string',
                'fiscal_code' => 'string',
                'sdi' => 'string',
                'pec' => 'string',
                'company_name' => 'string',
                'address_line_1' => 'string',
                'address_line_2' => 'string',
                'city' => 'string',
                'state' => 'string',
                'zip' => 'string',
                'birth_date' => 'date',
                'birth_place' => 'string',
                'notes' => 'string',
                'created_at' => 'date',
                'updated_at' => 'date',
                'deleted_at' => 'date',
            ],
            'brand' => [
                'name' => 'string',
                'slug' => 'string',
                'abstract' => 'string',
                'description' => 'string',
                'active' => 'boolean',
                'show_in_menu' => 'boolean',
                'in_evidence' => 'boolean',
                'sort_order' => 'integer',
                'created_at' => 'date',
                'updated_at' => 'date',
                'deleted_at' => 'date',
            ],
            'cart' => [
                'user_id' => 'integer',
                'order_id' => 'integer',
                'shipping_method_id' => 'integer',
                'shipping_zone_id' => 'integer',
                'identifier' => 'string',
                'status' => 'string',
                'sub_total_taxable' => 'numeric',
                'sub_total_tax' => 'numeric',
                'sub_total' => 'numeric',
                'shipping_fee' => 'numeric',
                'specific_weight' => 'numeric',
                'volumetric_weight' => 'numeric',
                'chargeable_weight' => 'numeric',
                'payment_fee' => 'numeric',
                'discount_code' => 'string',
                'discount_amount' => 'numeric',
                'total_final' => 'numeric',
                'user_first_name' => 'string',
                'user_last_name' => 'string',
                'user_email' => 'string',
                'notes' => 'string',
                'created_at' => 'date',
                'updated_at' => 'date',
                'deleted_at' => 'date',
            ],
            'cart_line' => [
                'cart_id' => 'integer',
                'product_id' => 'integer',
                'currency_id' => 'integer',
                'free_gift_id' => 'integer',
                'is_free_gift' => 'boolean',
                'discount_id' => 'integer',
                'product_name' => 'string',
                'product_sku' => 'string',
                'discount_code' => 'string',
                'discount_amount' => 'numeric',
                'unit_price' => 'numeric',
                'purchase_price' => 'numeric',
                'unit_discount' => 'numeric',
                'unit_final_price' => 'numeric',
                'unit_final_price_tax' => 'numeric',
                'unit_final_price_taxable' => 'numeric',
                'qty' => 'integer',
                'total_final_price' => 'numeric',
                'tax_rate' => 'numeric',
                'created_at' => 'date',
                'updated_at' => 'date',
                'deleted_at' => 'date',
            ],
            'country' => [
                'currency_id' => 'integer',
                'name' => 'string',
                'iso_2' => 'string',
                'iso_3' => 'string',
                'phone_code' => 'string',
                'flag_emoji' => 'string',
                'capital' => 'string',
                'native' => 'string',
                'created_at' => 'date',
                'updated_at' => 'date',
                'deleted_at' => 'date',
            ],
            'country_tax_class' => [
                'country_id' => 'integer',
                'tax_class_id' => 'integer',
                'rate' => 'numeric',
                'created_at' => 'date',
                'updated_at' => 'date',
            ],
            'currency' => [
                'name' => 'string',
                'code' => 'string',
                'symbol' => 'string',
                'exchange_rate' => 'numeric',
                'decimal_places' => 'integer',
                'is_enabled' => 'boolean',
                'is_default' => 'boolean',
                'created_at' => 'date',
                'updated_at' => 'date',
                'deleted_at' => 'date',
            ],
            'discount' => [
                'discountable_type' => 'string',
                'discountable_id' => 'integer',
                'type' => 'string',
                'value' => 'numeric',
                'name' => 'string',
                'code' => 'string',
                'active' => 'boolean',
                'starts_at' => 'date',
                'ends_at' => 'date',
                'uses' => 'integer',
                'max_uses' => 'integer',
                'apply_to_cart_total' => 'boolean',
                'apply_once_per_cart' => 'boolean',
                'max_uses_per_user' => 'integer',
                'one_per_user' => 'boolean',
                'free_shipping' => 'boolean',
                'first_purchase_only' => 'boolean',
                'minimum_order_total' => 'numeric',
                'priority' => 'integer',
                'stop_after_propagation' => 'boolean',
                'created_at' => 'date',
                'updated_at' => 'date',
                'deleted_at' => 'date',
            ],
            'discount_application' => [
                'discount_id' => 'integer',
                'discountable_type' => 'string',
                'discountable_id' => 'integer',
                'user_id' => 'integer',
                'cart_id' => 'integer',
                'order_id' => 'integer',
                'order_line_id' => 'integer',
                'qty' => 'integer',
                'amount' => 'numeric',
                'created_at' => 'date',
                'updated_at' => 'date',
                'deleted_at' => 'date',
            ],
            'free_gift' => [
                'name' => 'string',
                'mode' => 'string',
                'selection_mode' => 'string',
                'allow_decline' => 'boolean',
                'active' => 'boolean',
                'starts_at' => 'date',
                'ends_at' => 'date',
                'minimum_cart_subtotal' => 'numeric',
                'maximum_cart_subtotal' => 'numeric',
                'minimum_cart_quantity' => 'integer',
                'maximum_cart_quantity' => 'integer',
                'product_match_mode' => 'string',
                'created_at' => 'date',
                'updated_at' => 'date',
                'deleted_at' => 'date',
            ],
            'inventory' => [
                'product_id' => 'integer',
                'currency_id' => 'integer',
                'stock' => 'integer',
                'stock_reserved' => 'integer',
                'stock_available' => 'integer',
                'stock_min' => 'integer',
                'minimum_reorder_quantity' => 'integer',
                'reorder_lead_days' => 'integer',
                'manage_stock' => 'boolean',
                'price' => 'numeric',
                'price_includes_tax' => 'boolean',
                'purchase_price' => 'numeric',
                'created_at' => 'date',
                'updated_at' => 'date',
                'deleted_at' => 'date',
            ],
            'municipality' => [
                'province_id' => 'integer',
                'name' => 'string',
                'country_zone' => 'string',
                'zip' => 'string',
                'phone_prefix' => 'string',
                'istat_code' => 'string',
                'cadastral_code' => 'string',
                'latitude' => 'numeric',
                'longitude' => 'numeric',
                'created_at' => 'date',
                'updated_at' => 'date',
                'deleted_at' => 'date',
            ],
            'order' => [
                'user_id' => 'integer',
                'shipping_status_id' => 'integer',
                'shipping_method_id' => 'integer',
                'shipping_zone_id' => 'integer',
                'identifier' => 'string',
                'status' => 'string',
                'tracking_code' => 'string',
                'tracking_link' => 'string',
                'last_tracked_at' => 'date',
                'courier_code' => 'string',
                'sub_total_taxable' => 'numeric',
                'sub_total_tax' => 'numeric',
                'sub_total' => 'numeric',
                'shipping_fee' => 'numeric',
                'specific_weight' => 'numeric',
                'volumetric_weight' => 'numeric',
                'chargeable_weight' => 'numeric',
                'payment_fee' => 'numeric',
                'discount_code' => 'string',
                'discount_amount' => 'numeric',
                'total_final' => 'numeric',
                'user_first_name' => 'string',
                'user_last_name' => 'string',
                'user_email' => 'string',
                'customer_notes' => 'string',
                'admin_notes' => 'string',
                'approved_at' => 'date',
                'created_at' => 'date',
                'updated_at' => 'date',
                'deleted_at' => 'date',
            ],
            'order_line' => [
                'order_id' => 'integer',
                'product_id' => 'integer',
                'currency_id' => 'integer',
                'free_gift_id' => 'integer',
                'is_free_gift' => 'boolean',
                'discount_id' => 'integer',
                'product_name' => 'string',
                'product_sku' => 'string',
                'discount_code' => 'string',
                'discount_amount' => 'numeric',
                'unit_price' => 'numeric',
                'purchase_price' => 'numeric',
                'unit_discount' => 'numeric',
                'unit_final_price' => 'numeric',
                'unit_final_price_tax' => 'numeric',
                'unit_final_price_taxable' => 'numeric',
                'qty' => 'integer',
                'total_final_price' => 'numeric',
                'tax_rate' => 'numeric',
                'requested_return_qty' => 'integer',
                'returned_qty' => 'integer',
                'has_return_requests' => 'boolean',
                'is_returned' => 'boolean',
                'is_fully_returned' => 'boolean',
                'created_at' => 'date',
                'updated_at' => 'date',
                'deleted_at' => 'date',
            ],
            'price_list' => [
                'name' => 'string',
                'code' => 'string',
                'active' => 'boolean',
                'description' => 'string',
                'created_at' => 'date',
                'updated_at' => 'date',
                'deleted_at' => 'date',
            ],
            'price_list_price' => [
                'product_id' => 'integer',
                'price_list_id' => 'integer',
                'price' => 'numeric',
                'purchase_price' => 'numeric',
                'price_includes_tax' => 'boolean',
                'is_default' => 'boolean',
                'created_at' => 'date',
                'updated_at' => 'date',
                'deleted_at' => 'date',
            ],
            'product' => [
                'parent_id' => 'integer',
                'brand_id' => 'integer',
                'product_type_id' => 'integer',
                'tax_class_id' => 'integer',
                'name' => 'string',
                'slug' => 'string',
                'status' => 'string',
                'active' => 'boolean',
                'new' => 'boolean',
                'in_evidence' => 'boolean',
                'sku' => 'string',
                'ean' => 'string',
                'visible_from' => 'date',
                'visible_until' => 'date',
                'description' => 'string',
                'description_short' => 'string',
                'measuring_unit' => 'string',
                'qty_for_unit' => 'integer',
                'length' => 'numeric',
                'width' => 'numeric',
                'height' => 'numeric',
                'weight' => 'numeric',
                'created_at' => 'date',
                'updated_at' => 'date',
                'deleted_at' => 'date',
            ],
            'product_category' => [
                'parent_id' => 'integer',
                'path' => 'string',
                'name' => 'string',
                'slug' => 'string',
                'abstract' => 'string',
                'description' => 'string',
                'active' => 'boolean',
                'show_in_menu' => 'boolean',
                'in_evidence' => 'boolean',
                'sort_order' => 'integer',
                'visible_from' => 'date',
                'visible_until' => 'date',
                'created_at' => 'date',
                'updated_at' => 'date',
                'deleted_at' => 'date',
            ],
            'product_collection' => [
                'name' => 'string',
                'slug' => 'string',
                'description' => 'string',
                'active' => 'boolean',
                'visible_from' => 'date',
                'visible_until' => 'date',
                'created_at' => 'date',
                'updated_at' => 'date',
                'deleted_at' => 'date',
            ],
            'tag' => [
                'parent_id' => 'integer',
                'product_type_id' => 'integer',
                'path' => 'string',
                'name' => 'string',
                'slug' => 'string',
                'abstract' => 'string',
                'description' => 'string',
                'active' => 'boolean',
                'show_in_menu' => 'boolean',
                'in_evidence' => 'boolean',
                'sort_order' => 'integer',
                'visible_from' => 'date',
                'visible_until' => 'date',
                'created_at' => 'date',
                'updated_at' => 'date',
                'deleted_at' => 'date',
            ],
            'product_custom_field' => [
                'product_type_id' => 'integer',
                'name' => 'string',
                'required' => 'boolean',
                'sort_order' => 'integer',
                'type' => 'string',
                'created_at' => 'date',
                'updated_at' => 'date',
                'deleted_at' => 'date',
            ],
            'product_type' => [
                'name' => 'string',
                'slug' => 'string',
                'active' => 'boolean',
                'is_default' => 'boolean',
                'created_at' => 'date',
                'updated_at' => 'date',
                'deleted_at' => 'date',
            ],
            'product_variant' => [
                'product_type_id' => 'integer',
                'name' => 'string',
                'accept_hex_color' => 'boolean',
                'sort_order' => 'integer',
                'created_at' => 'date',
                'updated_at' => 'date',
                'deleted_at' => 'date',
            ],
            'product_variant_option' => [
                'product_variant_id' => 'integer',
                'name' => 'string',
                'hex_color' => 'string',
                'sort_order' => 'integer',
                'created_at' => 'date',
                'updated_at' => 'date',
                'deleted_at' => 'date',
            ],
            'province' => [
                'region_id' => 'integer',
                'name' => 'string',
                'code' => 'string',
                'created_at' => 'date',
                'updated_at' => 'date',
                'deleted_at' => 'date',
            ],
            'region' => [
                'country_id' => 'integer',
                'name' => 'string',
                'code' => 'string',
                'created_at' => 'date',
                'updated_at' => 'date',
                'deleted_at' => 'date',
            ],
            'return_reason' => [
                'code' => 'string',
                'name' => 'string',
                'description' => 'string',
                'active' => 'boolean',
                'sort_order' => 'integer',
                'created_at' => 'date',
                'updated_at' => 'date',
                'deleted_at' => 'date',
            ],
            'return_request' => [
                'order_id' => 'integer',
                'user_id' => 'integer',
                'return_reason_id' => 'integer',
                'description' => 'string',
                'notes' => 'string',
                'is_accepted' => 'boolean',
                'is_verified' => 'boolean',
                'created_at' => 'date',
                'updated_at' => 'date',
                'deleted_at' => 'date',
            ],
            'shipping_status' => [
                'external_code' => 'string',
                'name' => 'string',
                'created_at' => 'date',
                'updated_at' => 'date',
                'deleted_at' => 'date',
            ],
            'shipping_method' => [
                'code' => 'string',
                'name' => 'string',
                'active' => 'boolean',
                'flat_fee' => 'numeric',
                'volumetric_divisor' => 'numeric',
                'created_at' => 'date',
                'updated_at' => 'date',
                'deleted_at' => 'date',
            ],
            'shipping_method_zone' => [
                'shipping_method_id' => 'integer',
                'shipping_zone_id' => 'integer',
                'active' => 'boolean',
                'over_weight_price_per_kg' => 'numeric',
                'created_at' => 'date',
                'updated_at' => 'date',
            ],
            'shipping_zone' => [
                'code' => 'string',
                'name' => 'string',
                'active' => 'boolean',
                'priority' => 'integer',
                'created_at' => 'date',
                'updated_at' => 'date',
                'deleted_at' => 'date',
            ],
            'tax_class' => [
                'name' => 'string',
                'is_default' => 'boolean',
                'created_at' => 'date',
                'updated_at' => 'date',
                'deleted_at' => 'date',
            ],
            'user' => [],
        ];
    }

    protected function authorizeIfConfigured(string $ability, mixed $arguments): void
    {
        if (!config('venditio.authorize_using_policies')) {
            return;
        }

        $arguments = $this->normalizeAuthorizationArguments($arguments);

        if (!$this->hasAuthorizationDefinition($ability, $arguments)) {
            return;
        }

        Gate::authorize($ability, $arguments);
    }

    protected function hasAuthorizationDefinition(string $ability, mixed $arguments): bool
    {
        if (Gate::has($ability)) {
            return true;
        }

        if (is_string($arguments) && class_exists($arguments)) {
            return Gate::getPolicyFor($arguments) !== null;
        }

        if (is_object($arguments)) {
            return Gate::getPolicyFor($arguments) !== null;
        }

        return false;
    }

    protected function normalizeAuthorizationArguments(mixed $arguments): mixed
    {
        if (!is_string($arguments)) {
            return $arguments;
        }

        $modelKey = array_search($arguments, $this->defaultAuthorizationModelMap(), true);

        if (!is_string($modelKey)) {
            return $arguments;
        }

        return config('venditio.models.' . $modelKey, $arguments);
    }

    protected function defaultAuthorizationModelMap(): array
    {
        return [
            'address' => \PictaStudio\Venditio\Models\Address::class,
            'brand' => \PictaStudio\Venditio\Models\Brand::class,
            'cart' => \PictaStudio\Venditio\Models\Cart::class,
            'cart_line' => \PictaStudio\Venditio\Models\CartLine::class,
            'country' => \PictaStudio\Venditio\Models\Country::class,
            'country_tax_class' => \PictaStudio\Venditio\Models\CountryTaxClass::class,
            'credit_note' => \PictaStudio\Venditio\Models\CreditNote::class,
            'currency' => \PictaStudio\Venditio\Models\Currency::class,
            'discount' => \PictaStudio\Venditio\Models\Discount::class,
            'discount_application' => \PictaStudio\Venditio\Models\DiscountApplication::class,
            'inventory' => \PictaStudio\Venditio\Models\Inventory::class,
            'invoice' => \PictaStudio\Venditio\Models\Invoice::class,
            'municipality' => \PictaStudio\Venditio\Models\Municipality::class,
            'order' => \PictaStudio\Venditio\Models\Order::class,
            'order_line' => \PictaStudio\Venditio\Models\OrderLine::class,
            'price_list' => \PictaStudio\Venditio\Models\PriceList::class,
            'price_list_price' => \PictaStudio\Venditio\Models\PriceListPrice::class,
            'product' => \PictaStudio\Venditio\Models\Product::class,
            'product_category' => \PictaStudio\Venditio\Models\ProductCategory::class,
            'product_collection' => \PictaStudio\Venditio\Models\ProductCollection::class,
            'product_custom_field' => \PictaStudio\Venditio\Models\ProductCustomField::class,
            'product_type' => \PictaStudio\Venditio\Models\ProductType::class,
            'product_variant' => \PictaStudio\Venditio\Models\ProductVariant::class,
            'product_variant_option' => \PictaStudio\Venditio\Models\ProductVariantOption::class,
            'province' => \PictaStudio\Venditio\Models\Province::class,
            'region' => \PictaStudio\Venditio\Models\Region::class,
            'shipping_method' => \PictaStudio\Venditio\Models\ShippingMethod::class,
            'shipping_method_zone' => \PictaStudio\Venditio\Models\ShippingMethodZone::class,
            'shipping_status' => \PictaStudio\Venditio\Models\ShippingStatus::class,
            'shipping_zone' => \PictaStudio\Venditio\Models\ShippingZone::class,
            'tag' => \PictaStudio\Venditio\Models\Tag::class,
            'tax_class' => \PictaStudio\Venditio\Models\TaxClass::class,
            'user' => \PictaStudio\Venditio\Models\User::class,
        ];
    }

    protected function removeImplicitScopesOverriddenByExplicitFilters(Builder $query, string $model, array $validatedFilters): void
    {
        if (
            array_key_exists('active', $validatedFilters)
            || array_key_exists('is_active', $validatedFilters)
        ) {
            $query->withoutGlobalScope(Active::class);
        }

        if ($model === 'product' && array_key_exists('status', $validatedFilters)) {
            $query->withoutGlobalScope(ProductStatusActive::class);
        }

        $dateScopedColumns = $this->dateScopedColumns()[$model] ?? [];

        if ($dateScopedColumns === []) {
            return;
        }

        foreach ($dateScopedColumns as $column) {
            if (
                array_key_exists($column, $validatedFilters)
                || array_key_exists($column . '_start', $validatedFilters)
                || array_key_exists($column . '_end', $validatedFilters)
            ) {
                $query->withoutGlobalScope(InDateRange::class);

                return;
            }
        }
    }

    protected function dateScopedColumns(): array
    {
        return [
            'discount' => ['starts_at', 'ends_at'],
            'product' => ['visible_from', 'visible_until'],
            'product_category' => ['visible_from', 'visible_until'],
            'product_collection' => ['visible_from', 'visible_until'],
            'tag' => ['visible_from', 'visible_until'],
        ];
    }

    protected function modelSupportsSoftDeletes(mixed $modelInstance): bool
    {
        return is_object($modelInstance)
            && in_array(SoftDeletes::class, class_uses_recursive($modelInstance), true);
    }
}
