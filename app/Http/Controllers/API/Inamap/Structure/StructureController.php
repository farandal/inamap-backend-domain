<?php

namespace Domain\App\Http\Controllers\API\Inamap\Structure;

use Domain\App\Http\Controllers\API\Inamap\PolygonController;
use Domain\App\Models\Inamap\Structure;

class StructureController extends PolygonController
{
    public $resource         = StructureResource::class;
    public $requestValidator = StructureRequest::class;
    public $modelFilter      = StructureFilter::class;
    public $_model           = Structure::class;

    protected bool $overlapIsError = false;
}
