<?php

namespace Domain\App\Http\Controllers\API\Inamap\Path;

use Domain\App\Http\Controllers\API\Inamap\InamapFilter;

class PathFilter extends InamapFilter
{
    public function direction($direction)
    {
        return $this->where('direction', $direction);
    }
}
