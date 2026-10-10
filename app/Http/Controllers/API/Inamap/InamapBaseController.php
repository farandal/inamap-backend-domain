<?php

namespace Domain\App\Http\Controllers\API\Inamap;

use App\Http\Controllers\API\System\ReactAdminBaseController;
use App\Support\ResponseHandler;
use Domain\App\Models\Inamap\Inamap;
use Domain\App\Models\Inamap\InamapModel;
use Domain\App\Models\Inamap\Layout;
use Domain\App\Services\Inamap\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Base CRUD de los recursos INAMAP sobre ReactAdminBaseController (core).
 *
 * - _setup() acota $this->model al alcance del usuario ANTES de cualquier
 *   operación (getList, getOne, update, delete, deleteMany...), así una fila
 *   de otro tenant simplemente no existe para el usuario.
 * - Las subclases declaran $_model, $resource, $requestValidator, $modelFilter
 *   y, si hace falta, $with para relaciones precargadas.
 */
abstract class InamapBaseController extends ReactAdminBaseController
{
    public $policy = InamapPolicy::class;

    /** @var list<string> */
    protected array $with = [];

    public function __construct()
    {
        $this->model = $this->_model::query();
    }

    public function _setup($request)
    {
        $this->model = $this->_model::query()->visibleTo($request->user());
    }

    public function _preList($request)
    {
        if ($this->with) {
            $this->model->with($this->with);
        }
    }

    public function _preGetOne($request)
    {
        $this->_preList($request);
    }

    public function _postList($data)
    {
        return $this->resource::collection($data);
    }

    public function _postGetOne($item)
    {
        return $this->resource::make($item);
    }

    public function _create($request)
    {
        $this->_authorize('create');
        $validated = app($this->requestValidator)->validated();

        $item = $this->_model::create(array_merge($validated, $this->ownership($request, $validated)));

        return $this->afterSave($request, $item->fresh($this->with));
    }

    public function _update($request, $id, $item)
    {
        $item = $this->found($item);
        $this->_authorize('manage', $item);

        $validated = app($this->requestValidator)->validated();
        unset($validated['tenant_id'], $validated['tenancy_id'], $validated['inamap_id']);

        $item->update($validated);

        return $this->afterSave($request, $item->fresh($this->with));
    }

    public function _delete($request, $id, $item)
    {
        $item = $this->found($item);
        $this->_authorize('delete', $item);
        $item->delete();

        // Respuesta propia: evita que la base vuelva a llamar delete().
        return ResponseHandler::json($this->resource::make($item));
    }

    /**
     * Columnas de propiedad (tenant/tenancy/inamap) para un registro nuevo.
     * Por defecto se heredan del layout indicado; Inamap y Category lo redefinen.
     */
    protected function ownership(Request $request, array $validated): array
    {
        $layout = $this->ownedLayout($request, $validated['layout_id'] ?? null);

        return [
            'tenant_id'  => $layout->tenant_id,
            'tenancy_id' => $layout->tenancy_id,
            'inamap_id'  => $layout->inamap_id,
        ];
    }

    protected function afterSave(Request $request, InamapModel $item, array $extra = [])
    {
        return ResponseHandler::json(array_merge(['data' => $this->resource::make($item)], $extra), $request->isMethod('post') ? 201 : 200);
    }

    protected function ownedLayout(Request $request, ?string $layoutId): Layout
    {
        $layout = $layoutId ? Layout::query()->visibleTo($request->user())->find($layoutId) : null;

        if (!$layout) {
            throw ValidationException::withMessages(['layout_id' => __('inamap.errors.layout_not_found')]);
        }

        return $layout;
    }

    protected function ownedInamap(Request $request, ?string $inamapId): Inamap
    {
        $inamap = $inamapId ? Inamap::query()->visibleTo($request->user())->find($inamapId) : null;

        if (!$inamap) {
            throw (new ModelNotFoundException())->setModel(Inamap::class, [$inamapId]);
        }

        return $inamap;
    }

    protected function tenantContext(): TenantContext
    {
        return app(TenantContext::class);
    }

    protected function found($item)
    {
        if (!$item) {
            throw (new ModelNotFoundException())->setModel($this->_model);
        }

        return $item;
    }
}
