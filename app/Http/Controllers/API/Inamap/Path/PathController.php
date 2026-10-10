<?php

namespace Domain\App\Http\Controllers\API\Inamap\Path;

use Domain\App\Http\Controllers\API\Inamap\InamapBaseController;
use Domain\App\Models\Inamap\Path;
use Illuminate\Validation\ValidationException;

class PathController extends InamapBaseController
{
    public $resource         = PathResource::class;
    public $requestValidator = PathRequest::class;
    public $modelFilter      = PathFilter::class;
    public $_model           = Path::class;

    public function _update($request, $id, $item)
    {
        $item = $this->found($item);

        // Un camino no cambia de layout: sus enlaces dependen de él.
        if ($request->filled('layout_id') && $request->input('layout_id') !== $item->layout_id) {
            throw ValidationException::withMessages(['layout_id' => __('inamap.errors.path_layout_immutable')]);
        }

        return parent::_update($request, $id, $item);
    }
}
