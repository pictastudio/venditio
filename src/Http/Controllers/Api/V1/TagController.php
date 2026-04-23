<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;
use PictaStudio\Venditio\Actions\Tags\{CreateTag, UpdateTag};
use PictaStudio\Venditio\Http\Controllers\Api\Controller;
use PictaStudio\Venditio\Http\Requests\V1\Tag\{StoreTagRequest, UpdateTagRequest};
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

    public function destroy(Tag $tag): JsonResponse
    {
        $this->authorizeIfConfigured('delete', $tag);

        $tag->delete();

        return response()->noContent();
    }

    protected function resolveTagIncludes(): array
    {
        return $this->resolveIncludes($this->allowedIncludesWithDiscounts(['product_type']));
    }

    protected function tagRelationsForIncludes(array $includes): array
    {
        $relations = [];

        if (in_array('product_type', $includes, true)) {
            $relations[] = 'productType';
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
