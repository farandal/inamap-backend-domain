<?php

namespace Domain\App\Http\Controllers\API\Inamap\Location;

use Domain\App\Http\Controllers\API\Inamap\InamapBaseController;
use Domain\App\Models\Inamap\Category;
use Domain\App\Models\Inamap\Location;
use Domain\App\Models\Inamap\Structure;
use Domain\App\Services\Inamap\Plan\InamapPlanLimits;
use Domain\App\Services\Inamap\Qr\QrService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LocationController extends InamapBaseController
{
    public $resource         = LocationResource::class;
    public $requestValidator = LocationRequest::class;
    public $modelFilter      = LocationFilter::class;
    public $_model           = Location::class;

    protected array $with = ['category'];

    public function _create($request)
    {
        $this->_authorize('create');
        $validated = app($this->requestValidator)->validated();
        $owner = $this->ownership($request, $validated);

        app(InamapPlanLimits::class)->enforce($owner['tenancy_id'], 'max_locations', $request->user());
        $this->checkReferences($request, $validated, $owner['inamap_id'], $validated['layout_id']);

        $item = Location::create(array_merge($validated, $owner));

        return $this->afterSave($request, $item->fresh($this->with));
    }

    public function _update($request, $id, $item)
    {
        $item = $this->found($item);
        $this->_authorize('manage', $item);

        $validated = app($this->requestValidator)->validated();
        unset($validated['tenant_id'], $validated['tenancy_id'], $validated['inamap_id']);

        if (isset($validated['layout_id']) && $validated['layout_id'] !== $item->layout_id
            && $this->ownedLayout($request, $validated['layout_id'])->inamap_id !== $item->inamap_id) {
            throw ValidationException::withMessages(['layout_id' => __('inamap.errors.layout_not_found')]);
        }

        $this->checkReferences($request, $validated, $item->inamap_id, $validated['layout_id'] ?? $item->layout_id);
        $item->update($validated);

        return $this->afterSave($request, $item->fresh($this->with));
    }

    /**
     * GET inamap/locations/{id}/qr?format=svg|png — QR de la ubicación: su
     * `qr_url` si la tiene, si no el visor enfocado en ella.
     */
    public function qr(Request $request, string $id)
    {
        $item = Location::query()->visibleTo($request->user())->findOrFail($id);
        $format = in_array($request->query('format'), QrService::FORMATS, true) ? $request->query('format') : 'svg';
        $url = $item->qr_url
            ?: rtrim((string) config('inamap.route_sessions.app_url'), '/') . "/inamap/{$item->inamap_id}?location={$item->id}";

        $qr = app(QrService::class)->render($url, $format);

        return response($qr['content'], 200, [
            'Content-Type'        => $qr['mime'],
            'Content-Disposition' => 'inline; filename="' . ($item->code ?: $item->id) . '.' . $format . '"',
        ]);
    }

    private function checkReferences(Request $request, array $validated, string $inamapId, string $layoutId): void
    {
        if (!empty($validated['structure_id'])) {
            $structure = Structure::query()->where('inamap_id', $inamapId)->find($validated['structure_id']);
            if (!$structure || $structure->layout_id !== $layoutId) {
                throw ValidationException::withMessages(['structure_id' => __('inamap.errors.structure_not_in_layout')]);
            }
        }

        if (!empty($validated['category_id'])
            && !Category::query()->visibleTo($request->user())->whereKey($validated['category_id'])->exists()) {
            throw ValidationException::withMessages(['category_id' => __('inamap.errors.category_not_found')]);
        }
    }
}
