<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;
use PictaStudio\Venditio\Actions\Brands\{CreateBrand, UpdateBrand};
use PictaStudio\Venditio\Http\Controllers\Api\Controller;
use PictaStudio\Venditio\Http\Requests\V1\Brand\{StoreBrandRequest, UpdateBrandRequest};
use PictaStudio\Venditio\Http\Resources\V1\BrandResource;
use PictaStudio\Venditio\Models\Brand;

use function PictaStudio\Venditio\Helpers\Functions\{query, resolve_model};

class BrandController extends Controller
{
    public function index(): JsonResource|JsonResponse
    {
        $this->authorizeIfConfigured('viewAny', resolve_model('brand'));

        $includes = $this->resolveBrandIncludes();
        $filters = request()->except('include');
        $query = query('brand')->with($this->brandRelationsForIncludes($includes));
        $this->applyBrandIndexRelationFilters($query, $filters);

        return BrandResource::collection(
            $this->applyBaseFilters($query, $filters, 'brand', $this->brandIndexValidationRules())
        );
    }

    public function store(StoreBrandRequest $request): JsonResource
    {
        $this->authorizeIfConfigured('create', resolve_model('brand'));
        $includes = $this->resolveBrandIncludes();

        $brand = app(CreateBrand::class)
            ->handle($request->validated());

        return BrandResource::make($brand->refresh()->load($this->brandRelationsForIncludes($includes, true)));
    }

    public function show(Brand $brand): JsonResource
    {
        $this->authorizeIfConfigured('view', $brand);
        $includes = $this->resolveBrandIncludes();

        return BrandResource::make($brand->loadMissing($this->brandRelationsForIncludes($includes, true)));
    }

    public function update(UpdateBrandRequest $request, Brand $brand): JsonResource
    {
        $this->authorizeIfConfigured('update', $brand);
        $includes = $this->resolveBrandIncludes();

        $brand = app(UpdateBrand::class)
            ->handle($brand, $request->validated());

        return BrandResource::make($brand->refresh()->load($this->brandRelationsForIncludes($includes, true)));
    }

    public function destroy(Brand $brand)
    {
        $this->authorizeIfConfigured('delete', $brand);

        $brand->delete();

        return response()->noContent();
    }

    protected function resolveBrandIncludes(): array
    {
        return $this->resolveIncludes($this->allowedIncludesWithDiscounts(['tags']));
    }

    protected function brandRelationsForIncludes(array $includes, bool $includeTagsByDefault = false): array
    {
        $relations = [];

        if ($includeTagsByDefault || in_array('tags', $includes, true)) {
            $relations[] = 'tags';
        }

        return [
            ...$relations,
            ...$this->discountRelationsForIncludes($includes),
        ];
    }

    protected function brandIndexValidationRules(): array
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

    protected function applyBrandIndexRelationFilters(Builder $query, array &$filters): void
    {
        if (isset($filters['tag_ids']) && is_array($filters['tag_ids'])) {
            $query->whereHas(
                'tags',
                fn (Builder $tagsQuery) => $tagsQuery->whereKey($filters['tag_ids'])
            );
        }
    }
}
