<?php

namespace Domain\App\Http\Controllers\API\Inamap\Layout;

use App\Support\ResponseHandler;
use App\Traits\HandlesImageUploads;
use Domain\App\Http\Controllers\API\Inamap\InamapBaseController;
use Domain\App\Models\Inamap\Layout;
use Domain\App\Services\Inamap\Plan\InamapPlanLimits;
use Illuminate\Http\Request;

class LayoutController extends InamapBaseController
{
    use HandlesImageUploads;

    public $resource         = LayoutResource::class;
    public $requestValidator = LayoutRequest::class;
    public $modelFilter      = LayoutFilter::class;
    public $_model           = Layout::class;

    public function _preList($request)
    {
        $this->model->orderBy('level')->orderBy('sort');
    }

    public function _create($request)
    {
        $this->_authorize('create');
        $validated = app($this->requestValidator)->validated();
        $inamap = $this->ownedInamap($request, $validated['inamap_id']);

        app(InamapPlanLimits::class)->enforce($inamap->tenancy_id, 'max_layouts_per_inamap', $request->user(), $inamap->id);

        unset($validated['image']);
        $item = Layout::create(array_merge($validated, [
            'tenant_id'  => $inamap->tenant_id,
            'tenancy_id' => $inamap->tenancy_id,
        ]));

        $this->storeImage($request, $item);

        return $this->afterSave($request, $item->fresh());
    }

    public function _update($request, $id, $item)
    {
        $item = $this->found($item);
        $this->_authorize('manage', $item);

        $validated = app($this->requestValidator)->validated();
        unset($validated['image'], $validated['inamap_id'], $validated['tenant_id'], $validated['tenancy_id']);

        $item->update($validated);
        $this->storeImage($request, $item);

        return $this->afterSave($request, $item->fresh());
    }

    /** POST inamap/layouts/{id}/image — sube o reemplaza la imagen de fondo. */
    public function image(Request $request, string $id)
    {
        $request->validate(['image' => 'required|file|mimes:jpg,jpeg,png,webp,svg|max:20480']);

        $item = Layout::query()->visibleTo($request->user())->findOrFail($id);
        $this->_authorize('manage', $item);
        $this->storeImage($request, $item);

        return ResponseHandler::json(['data' => LayoutResource::make($item->fresh())]);
    }

    private function storeImage(Request $request, Layout $item): void
    {
        if (!$request->hasFile('image')) {
            return;
        }

        $data = [];
        $result = $this->handleImageUpload($request, $data, $item->tenancy_id, $item->tenant_id, 'inamap_layout', 'image_path', 'image', (string) $item->id);
        $path = $data['image_path'] ?? ($result['path'] ?? null);

        if ($path) {
            $item->update(['image_path' => $path]);
        }
    }
}
