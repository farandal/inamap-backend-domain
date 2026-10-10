<?php

namespace Domain\App\Http\Controllers\API\Inamap\Category;

use App\Support\ResponseHandler;
use App\Traits\HandlesImageUploads;
use Domain\App\Http\Controllers\API\Inamap\InamapBaseController;
use Domain\App\Models\Inamap\Category;
use Domain\App\Models\Inamap\Location;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Categorías de ubicaciones, con el mismo contrato que las categorías de
 * catálogo del framework:
 * - getList devuelve las raíces con su árbol (`subcategoriesTree`), salvo que
 *   se pida `flat=true` (lista plana con `breadcrumbed_name`), `category_id` o `id`.
 * - POST updateTree recibe el árbol completo editado ({id?, name, tree_index, subcategories}).
 * - Un TenancyAdmin sin tenant activo crea categorías de alcance tenancy.
 */
class CategoryController extends InamapBaseController
{
    use HandlesImageUploads;

    public $resource         = CategoryResource::class;
    public $requestValidator = CategoryRequest::class;
    public $modelFilter      = CategoryFilter::class;
    public $_model           = Category::class;

    public function _preList($request)
    {
        $this->model
            ->with(['category', 'subcategoriesTree'])
            ->when(!$request->order, fn ($q) => $q->orderBy('tree_index')->orderBy('name'))
            ->when(
                !$request->boolean('flat') && !$request->has('category_id') && !$request->id && !$request->ids,
                fn ($q) => $q->whereNull('category_id'),
            );
    }

    public function _preGetOne($request)
    {
        $this->model->with(['category', 'subcategoriesTree']);
    }

    public function _postList($data)
    {
        if (request()->boolean('flat')) {
            $items = $data instanceof LengthAwarePaginator ? $data->getCollection() : $data;
            $items = $items->map(fn ($item) => $item->append('breadcrumbed_name')->unsetRelation('subcategoriesTree'))
                ->sortBy('breadcrumbed_name')
                ->values();

            $data instanceof LengthAwarePaginator ? $data->setCollection($items) : $data = $items;
        }

        return CategoryResource::collection($data);
    }

    protected function ownership(Request $request, array $validated): array
    {
        $user = $request->user();
        $tenancyWide = $user->hasRole('TenancyAdmin')
            && !$request->header('X-Tenant-Id')
            && empty($validated['tenant_id']);

        $owner = $tenancyWide
            ? ['tenant_id' => null, 'tenancy_id' => $user->tenancy_id]
            : $this->tenantContext()->forWrite($user, $validated['tenant_id'] ?? null);

        $this->checkParent($request, $validated['category_id'] ?? null);

        return $owner;
    }

    public function _create($request)
    {
        $this->_authorize('create');
        $validated = app($this->requestValidator)->validated();
        unset($validated['image']);

        $item = Category::create(array_merge($validated, $this->ownership($request, $validated)));
        $this->storeImage($request, $item);

        return $this->afterSave($request, $item->fresh());
    }

    public function _update($request, $id, $item)
    {
        $item = $this->found($item);
        $this->_authorize('manage', $item);

        $validated = app($this->requestValidator)->validated();
        unset($validated['image'], $validated['tenant_id'], $validated['tenancy_id']);

        if (array_key_exists('category_id', $validated)) {
            $this->checkParent($request, $validated['category_id'], $item);
        }

        $item->update($validated);
        $this->storeImage($request, $item);

        return $this->afterSave($request, $item->fresh());
    }

    /** Una categoría con ubicaciones (propias o de subcategorías) no se elimina sin reasignarlas. */
    public function _delete($request, $id, $item)
    {
        $item = $this->found($item);
        $this->_authorize('delete', $item);

        $ids = $this->descendantIds($item->id);
        if (Location::query()->whereIn('category_id', $ids)->exists()) {
            abort(409, __('inamap.errors.category_in_use'));
        }

        Category::query()->whereIn('id', $ids)->get()->each->delete();

        return ResponseHandler::json(CategoryResource::make($item));
    }

    /** POST inamap/categories/updateTree — reemplaza el árbol del tenant activo. */
    public function updateTree(Request $request)
    {
        $request->validate(['updatedTree' => 'present|array']);
        $owner = $this->tenantContext()->forWrite($request->user(), $request->input('tenant_id'));

        DB::transaction(function () use ($request, $owner) {
            $existing = Category::query()->where('tenant_id', $owner['tenant_id'])->pluck('id')->all();
            $kept = [];
            $this->saveTree($request->input('updatedTree', []), null, $owner, $existing, $kept);

            $removed = array_diff($existing, $kept);
            if ($removed && Location::query()->whereIn('category_id', $removed)->exists()) {
                abort(409, __('inamap.errors.category_in_use'));
            }
            Category::query()->whereIn('id', $removed)->get()->each->delete();
        });

        return ResponseHandler::json(['data' => true]);
    }

    private function saveTree(array $nodes, ?string $parentId, array $owner, array $existing, array &$kept): void
    {
        foreach (array_values($nodes) as $index => $node) {
            $attributes = [
                'name'        => (string) ($node['name'] ?? ''),
                'category_id' => $parentId,
                'tree_index'  => (int) ($node['tree_index'] ?? $index),
            ];

            if (!empty($node['id']) && in_array($node['id'], $existing, true)) {
                Category::whereKey($node['id'])->update($attributes);
                $id = $node['id'];
            } else {
                $id = Category::create(array_merge($attributes, $owner))->id;
            }

            $kept[] = $id;
            $this->saveTree($node['subcategories'] ?? [], $id, $owner, $existing, $kept);
        }
    }

    private function checkParent(Request $request, ?string $parentId, ?Category $item = null): void
    {
        if (!$parentId) {
            return;
        }

        $parent = Category::query()->visibleTo($request->user())->find($parentId);
        $cycle = $item && in_array($parentId, $this->descendantIds($item->id), true);

        if (!$parent || $cycle) {
            throw ValidationException::withMessages(['category_id' => __('inamap.errors.category_parent_invalid')]);
        }
    }

    /** @return list<string> la categoría y todas sus descendientes */
    private function descendantIds(string $id): array
    {
        $ids = [$id];
        $frontier = [$id];
        while ($frontier) {
            $frontier = Category::query()->whereIn('category_id', $frontier)->pluck('id')->diff($ids)->values()->all();
            $ids = [...$ids, ...$frontier];
        }

        return $ids;
    }

    private function storeImage(Request $request, Category $item): void
    {
        if (!$request->hasFile('image')) {
            return;
        }

        $data = [];
        $result = $this->handleImageUpload($request, $data, $item->tenancy_id, $item->tenant_id, 'inamap_category', 'image_path', 'image', (string) $item->id);
        if ($path = $data['image_path'] ?? ($result['path'] ?? null)) {
            $item->update(['image_path' => $path]);
        }
    }
}
