<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;
use PictaStudio\Venditio\Actions\ProductTags\{CreateProductTag, UpdateProductTag};
use PictaStudio\Venditio\Http\Controllers\Api\Controller;
use PictaStudio\Venditio\Http\Requests\V1\ProductTag\{StoreProductTagRequest, UpdateProductTagRequest};
use PictaStudio\Venditio\Http\Resources\V1\ProductTagResource;
use PictaStudio\Venditio\Models\ProductTag;

use function PictaStudio\Venditio\Helpers\Functions\{query, resolve_model};

class ProductTagController extends Controller
{
    public function index(): JsonResource|JsonResponse
    {
        $this->authorizeIfConfigured('viewAny', ProductTag::class);

        $filters = request()->all();
        $this->validateData($filters, [
            'as_tree' => ['boolean'],
        ]);

        $asTree = request()->boolean('as_tree');
        $includes = $this->resolveProductTagIncludes();
        unset($filters['as_tree'], $filters['include']);

        $query = query('product_tag')->with($this->productTagRelationsForIncludes($includes));

        if ($asTree) {
            return ProductTagResource::collection(
                $this->applyBaseFilters(
                    $query,
                    [
                        ...$filters,
                        'all' => true,
                    ],
                    'product_tag',
                    $this->productTagIndexValidationRules()
                )->tree()
            );
        }

        return ProductTagResource::collection(
            $this->applyBaseFilters($query, $filters, 'product_tag', $this->productTagIndexValidationRules())
        );
    }

    public function store(StoreProductTagRequest $request): JsonResource
    {
        $this->authorizeIfConfigured('create', ProductTag::class);

        $tag = app(CreateProductTag::class)
            ->handle($request->validated());

        return ProductTagResource::make($tag);
    }

    public function show(ProductTag $productTag): JsonResource
    {
        $this->authorizeIfConfigured('view', $productTag);

        $includes = $this->resolveProductTagIncludes();

        return ProductTagResource::make($productTag->load($this->productTagRelationsForIncludes($includes)));
    }

    public function update(UpdateProductTagRequest $request, ProductTag $productTag): JsonResource
    {
        $this->authorizeIfConfigured('update', $productTag);

        $tag = app(UpdateProductTag::class)
            ->handle($productTag, $request->validated());

        return ProductTagResource::make($tag);
    }

    public function destroy(ProductTag $productTag): JsonResponse
    {
        $this->authorizeIfConfigured('delete', $productTag);

        $productTag->delete();

        return response()->noContent();
    }

    protected function resolveProductTagIncludes(): array
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
            'include.*' => ['string', Rule::in(['product_type'])],
        ]);

        return $includes;
    }

    protected function productTagRelationsForIncludes(array $includes): array
    {
        $relations = [];

        if (in_array('product_type', $includes, true)) {
            $relations[] = 'productType';
        }

        return $relations;
    }

    protected function productTagIndexValidationRules(): array
    {
        $productTypeModel = app(resolve_model('product_type'));
        $productTypeTable = method_exists($productTypeModel, 'getTableName')
            ? $productTypeModel->getTableName()
            : $productTypeModel->getTable();

        return [
            'product_type_id' => [
                'sometimes',
                'integer',
                Rule::exists($productTypeTable, $productTypeModel->getKeyName()),
            ],
        ];
    }
}
