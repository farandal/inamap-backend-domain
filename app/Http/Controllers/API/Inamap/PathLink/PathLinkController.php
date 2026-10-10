<?php

namespace Domain\App\Http\Controllers\API\Inamap\PathLink;

use Domain\App\Http\Controllers\API\Inamap\InamapBaseController;
use Domain\App\Models\Inamap\Path;
use Domain\App\Models\Inamap\PathLink;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PathLinkController extends InamapBaseController
{
    public $resource         = PathLinkResource::class;
    public $requestValidator = PathLinkRequest::class;
    public $modelFilter      = PathLinkFilter::class;
    public $_model           = PathLink::class;

    protected function ownership(Request $request, array $validated): array
    {
        $from = $this->checkPaths($request, $validated);

        return ['tenant_id' => $from->tenant_id, 'tenancy_id' => $from->tenancy_id, 'inamap_id' => $from->inamap_id];
    }

    public function _update($request, $id, $item)
    {
        $item = $this->found($item);
        $this->checkPaths($request, array_merge($item->only([
            'from_path_id', 'to_path_id', 'kind', 'vertical_type',
        ]), $request->only(['from_path_id', 'to_path_id', 'kind', 'vertical_type'])));

        return parent::_update($request, $id, $item);
    }

    /**
     * Ambos caminos visibles y del mismo inamap. `vertical` une layouts
     * distintos y exige tipo; `link`/`vertex` unen caminos de una misma planta.
     */
    private function checkPaths(Request $request, array $data): Path
    {
        $from = Path::query()->visibleTo($request->user())->find($data['from_path_id'] ?? null);
        $to = Path::query()->visibleTo($request->user())->find($data['to_path_id'] ?? null);

        if (!$from || !$to || $from->inamap_id !== $to->inamap_id) {
            throw ValidationException::withMessages(['to_path_id' => __('inamap.errors.link_paths_invalid')]);
        }

        if ($from->id === $to->id) {
            throw ValidationException::withMessages(['to_path_id' => __('inamap.errors.link_same_path')]);
        }

        $vertical = ($data['kind'] ?? 'link') === 'vertical';

        if ($vertical && $from->layout_id === $to->layout_id) {
            throw ValidationException::withMessages(['kind' => __('inamap.errors.vertical_same_layout')]);
        }

        if (!$vertical && $from->layout_id !== $to->layout_id) {
            throw ValidationException::withMessages(['kind' => __('inamap.errors.link_cross_layout')]);
        }

        if ($vertical && empty($data['vertical_type'])) {
            throw ValidationException::withMessages(['vertical_type' => __('inamap.errors.vertical_type_required')]);
        }

        return $from;
    }
}
