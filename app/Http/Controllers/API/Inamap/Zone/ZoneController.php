<?php

namespace Domain\App\Http\Controllers\API\Inamap\Zone;

use Domain\App\Http\Controllers\API\Inamap\PolygonController;
use Domain\App\Models\Inamap\Zone;

class ZoneController extends PolygonController
{
    public $resource         = ZoneResource::class;
    public $requestValidator = ZoneRequest::class;
    public $modelFilter      = ZoneFilter::class;
    public $_model           = Zone::class;

    protected bool $overlapIsError = true;
}
