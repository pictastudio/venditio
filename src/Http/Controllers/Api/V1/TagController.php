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

        $tag = app(CreateTag::class)
            ->handle($request->validated());

        return TagResource::make($tag);
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

        $updatedTag = app(UpdateTag::class)
            ->handle($tag, $request->validated());

        return TagResource::make($updatedTag);
    }

    public function destroy(Tag $tag): JsonResponse
    {
        $this->authorizeIfConfigured('delete', $tag);

        $tag->delete();

        return response()->noContent();
    }

    protected function resolveTagIncludes(): array
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

    protected function tagRelationsForIncludes(array $includes): array
    {
        $relations = [];

        if (in_array('product_type', $includes, true)) {
            $relations[] = 'productType';
        }

        return $relations;
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
