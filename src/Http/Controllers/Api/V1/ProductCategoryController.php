<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\{Rule, ValidationException};
use PictaStudio\Venditio\Actions\ProductCategories\{CreateProductCategory, UpdateMultipleProductCategories, UpdateProductCategory};
use PictaStudio\Venditio\Http\Controllers\Api\Controller;
use PictaStudio\Venditio\Http\Requests\V1\ProductCategory\{StoreProductCategoryRequest, UpdateMultipleProductCategoryRequest, UpdateProductCategoryRequest};
use PictaStudio\Venditio\Http\Resources\V1\ProductCategoryResource;
use PictaStudio\Venditio\Models\ProductCategory;

use function PictaStudio\Venditio\Helpers\Functions\{query, resolve_model};

class ProductCategoryController extends Controller
{
    public function index(): JsonResource|JsonResponse
    {
        $this->authorizeIfConfigured('viewAny', ProductCategory::class);

        $filters = request()->all();
        $this->validateData($filters, [
            'as_tree' => [
                'boolean',
            ],
        ]);

        $asTree = request()->boolean('as_tree');
        $includes = $this->resolveProductCategoryIncludes();
        unset($filters['as_tree'], $filters['include']);
        $query = query('product_category')->with($this->productCategoryRelationsForIncludes($includes));
        $this->applyProductCategoryIndexRelationFilters($query, $filters);

        if ($asTree) {
            return ProductCategoryResource::collection(
                $this->applyBaseFilters(
                    $query,
                    [
                        ...$filters,
                        'all' => true,
                    ],
                    'product_category',
                    $this->productCategoryIndexValidationRules()
                )->tree()
            );
        }

        return ProductCategoryResource::collection(
            $this->applyBaseFilters(
                $query,
                $filters,
                'product_category',
                $this->productCategoryIndexValidationRules()
            )
        );
    }

    public function store(StoreProductCategoryRequest $request)
    {
        $this->authorizeIfConfigured('create', ProductCategory::class);
        $includes = $this->resolveProductCategoryIncludes();

        $category = app(CreateProductCategory::class)
            ->handle($request->validated());

        return ProductCategoryResource::make($category->load($this->productCategoryRelationsForIncludes($includes)));
    }

    public function show(ProductCategory $productCategory): JsonResource
    {
        $this->authorizeIfConfigured('view', $productCategory);
        $includes = $this->resolveProductCategoryIncludes();

        return ProductCategoryResource::make($productCategory->load($this->productCategoryRelationsForIncludes($includes)));
    }

    public function update(UpdateProductCategoryRequest $request, ProductCategory $productCategory)
    {
        $this->authorizeIfConfigured('update', $productCategory);
        $includes = $this->resolveProductCategoryIncludes();

        $category = app(UpdateProductCategory::class)
            ->handle($productCategory, $request->validated());

        return ProductCategoryResource::make($category->load($this->productCategoryRelationsForIncludes($includes)));
    }

    public function updateMultiple(UpdateMultipleProductCategoryRequest $request): JsonResource
    {
        $validated = $request->validated();
        $categoryIds = collect($validated['categories'])
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $categories = query('product_category')
            ->whereKey($categoryIds)
            ->get()
            ->keyBy(fn (ProductCategory $category): int => (int) $category->getKey());

        if ($categories->count() !== count($categoryIds)) {
            $missingIds = collect($categoryIds)
                ->diff($categories->keys())
                ->values()
                ->all();

            throw ValidationException::withMessages([
                'categories' => [
                    'Some categories are not available for update: ' . implode(', ', $missingIds),
                ],
            ]);
        }

        foreach ($categoryIds as $categoryId) {
            $this->authorizeIfConfigured('update', $categories->get($categoryId));
        }

        $updatedCategories = app(UpdateMultipleProductCategories::class)
            ->handle($validated['categories']);

        return ProductCategoryResource::collection($updatedCategories);
    }

    public function destroy(ProductCategory $productCategory)
    {
        $this->authorizeIfConfigured('delete', $productCategory);

        $productCategory->delete();

        return response()->noContent();
    }

    protected function resolveProductCategoryIncludes(): array
    {
        return $this->resolveIncludes($this->allowedIncludesWithDiscounts(['tags']));
    }

    protected function productCategoryRelationsForIncludes(array $includes): array
    {
        $relations = [];

        if (in_array('tags', $includes, true)) {
            $relations[] = 'tags';
        }

        return [
            ...$relations,
            ...$this->discountRelationsForIncludes($includes),
        ];
    }

    protected function productCategoryIndexValidationRules(): array
    {
        $tagModel = app(resolve_model('tag'));
        $tagTable = method_exists($tagModel, 'getTableName')
            ? $tagModel->getTableName()
            : $tagModel->getTable();

        return [
            'tag_ids' => ['sometimes', 'array', 'min:1'],
            'tag_ids.*' => [
                'integer',
                Rule::exists($tagTable, $tagModel->getKeyName()),
            ],
        ];
    }

    protected function applyProductCategoryIndexRelationFilters(Builder $query, array &$filters): void
    {
        if (isset($filters['tag_ids']) && is_array($filters['tag_ids'])) {
            $query->whereHas(
                'tags',
                fn (Builder $tagsQuery) => $tagsQuery->whereKey($filters['tag_ids'])
            );
        }
    }
}
