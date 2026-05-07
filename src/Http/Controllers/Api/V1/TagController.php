<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Http\{JsonResponse, Response};
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\{Rule, ValidationException};
use PictaStudio\Venditio\Actions\CatalogImages\DeleteCatalogImage;
use PictaStudio\Venditio\Actions\Tags\{CreateTag, UpdateMultipleTags, UpdateTag};
use PictaStudio\Venditio\Actions\Tree\RebuildTreePaths;
use PictaStudio\Venditio\Http\Controllers\Api\Controller;
use PictaStudio\Venditio\Http\Requests\V1\Tag\{StoreTagRequest, UpdateMultipleTagRequest, UpdateTagRequest};
use PictaStudio\Venditio\Http\Resources\V1\TagResource;
use PictaStudio\Venditio\Models\Tag;

use function PictaStudio\Venditio\Helpers\Functions\{query, resolve_model};

class TagController extends Controller
{
    public function index(): JsonResource|JsonResponse
    {
        $this->authorizeIfConfigured('viewAny', Tag::class);

        $filters = request()->all();
        $this->validateData($filters, [
            'as_tree' => ['boolean'],
        ]);

        $asTree = request()->boolean('as_tree');
        $includes = $this->resolveTagIncludes();
        unset($filters['as_tree'], $filters['include']);

        $query = query('tag')->with($this->tagRelationsForIncludes($includes));

        if ($asTree) {
            return TagResource::collection(
                $this->applyBaseFilters(
                    $query,
                    [
                        ...$filters,
                        'all' => true,
                    ],
                    'tag',
                    $this->tagIndexValidationRules()
                )->tree()
            );
        }

        return TagResource::collection(
            $this->applyBaseFilters($query, $filters, 'tag', $this->tagIndexValidationRules())
        );
    }

    public function store(StoreTagRequest $request): JsonResource
    {
        $this->authorizeIfConfigured('create', Tag::class);

        $includes = $this->resolveTagIncludes();
        $tag = app(CreateTag::class)
            ->handle($request->validated());

        return TagResource::make($tag->load($this->tagRelationsForIncludes($includes)));
    }

    public function show(Tag $tag): JsonResource
    {
        $this->authorizeIfConfigured('view', $tag);

        $includes = $this->resolveTagIncludes();

        return TagResource::make($tag->load($this->tagRelationsForIncludes($includes)));
    }

    public function update(UpdateTagRequest $request, Tag $tag): JsonResource
    {
        $this->authorizeIfConfigured('update', $tag);

        $includes = $this->resolveTagIncludes();
        $updatedTag = app(UpdateTag::class)
            ->handle($tag, $request->validated());

        return TagResource::make($updatedTag->load($this->tagRelationsForIncludes($includes)));
    }

    public function updateMultiple(UpdateMultipleTagRequest $request): JsonResource
    {
        $validated = $request->validated();
        $tagIds = collect($validated['tags'])
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $tags = query('tag')
            ->whereKey($tagIds)
            ->get()
            ->keyBy(fn (Tag $tag): int => (int) $tag->getKey());

        if ($tags->count() !== count($tagIds)) {
            $missingIds = collect($tagIds)
                ->diff($tags->keys())
                ->values()
                ->all();

            throw ValidationException::withMessages([
                'tags' => [
                    'Some tags are not available for update: ' . implode(', ', $missingIds),
                ],
            ]);
        }

        foreach ($tagIds as $tagId) {
            $this->authorizeIfConfigured('update', $tags->get($tagId));
        }

        $updatedTags = app(UpdateMultipleTags::class)
            ->handle($validated['tags']);

        return TagResource::collection($updatedTags);
    }

    public function destroy(Tag $tag, RebuildTreePaths $treePaths): Response
    {
        $this->authorizeIfConfigured('delete', $tag);

        if (!request()->boolean('force') && $tag->products()->withoutGlobalScopes()->exists()) {
            throw ValidationException::withMessages([
                'products' => [
                    'This tag has connected products. Use force=1 to delete it and detach related products.',
                ],
            ]);
        }

        DB::transaction(function () use ($tag, $treePaths): void {
            $tagKey = $tag->getKey();
            $tagMorphClass = $tag->getMorphClass();

            DB::table('taggables')
                ->where('tag_id', $tagKey)
                ->orWhere(function ($query) use ($tagKey, $tagMorphClass): void {
                    $query->where('taggable_type', $tagMorphClass)
                        ->where('taggable_id', $tagKey);
                })
                ->delete();

            $treePaths->releaseChildrenToRoot($tag);

            $tag->delete();
        });

        return response()->noContent();
    }

    public function destroyImage(Tag $tag, string $imageId, DeleteCatalogImage $action)
    {
        $this->authorizeIfConfigured('update', $tag);

        $action->handle($tag, $imageId);

        return response()->noContent();
    }

    protected function resolveTagIncludes(): array
    {
        return $this->resolveIncludes($this->allowedIncludesWithDiscounts([
            'product_type',
            'products',
            'brands',
            'product_categories',
            'product_collections',
            'tags',
        ]));
    }

    protected function tagRelationsForIncludes(array $includes): array
    {
        $relations = [];

        if (in_array('product_type', $includes, true)) {
            $relations[] = 'productType';
        }

        if (in_array('products', $includes, true)) {
            $relations[] = 'products';
        }

        if (in_array('brands', $includes, true)) {
            $relations[] = 'brands';
        }

        if (in_array('product_categories', $includes, true)) {
            $relations[] = 'productCategories';
        }

        if (in_array('product_collections', $includes, true)) {
            $relations[] = 'productCollections';
        }

        if (in_array('tags', $includes, true)) {
            $relations[] = 'tags';
        }

        return [
            ...$relations,
            ...$this->discountRelationsForIncludes($includes),
        ];
    }

    protected function tagIndexValidationRules(): array
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
