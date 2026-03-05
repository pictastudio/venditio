<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
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

        $filters = request()->all();

        return BrandResource::collection(
            $this->applyBaseFilters(query('brand'), $filters, 'brand')
        );
    }

    public function store(StoreBrandRequest $request): JsonResource
    {
        $this->authorizeIfConfigured('create', resolve_model('brand'));

        $payload = $request->validated();
        $tagIds = Arr::pull($payload, 'tag_ids', []);

        $brand = query('brand')->create($payload);
        $brand->tags()->sync($tagIds);

        return BrandResource::make($brand->refresh()->load('tags'));
    }

    public function show(Brand $brand): JsonResource
    {
        $this->authorizeIfConfigured('view', $brand);

        return BrandResource::make($brand->loadMissing('tags'));
    }

    public function update(UpdateBrandRequest $request, Brand $brand): JsonResource
    {
        $this->authorizeIfConfigured('update', $brand);

        $payload = $request->validated();
        $tagIdsProvided = array_key_exists('tag_ids', $payload);
        $tagIds = Arr::pull($payload, 'tag_ids', []);

        $brand->fill($payload);
        $brand->save();

        if ($tagIdsProvided) {
            $brand->tags()->sync($tagIds);
        }

        return BrandResource::make($brand->refresh()->load('tags'));
    }

    public function destroy(Brand $brand)
    {
        $this->authorizeIfConfigured('delete', $brand);

        $brand->delete();

        return response()->noContent();
    }
}
