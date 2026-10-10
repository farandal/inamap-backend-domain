<?php

namespace Domain\App\Http\Controllers\API\Inamap\Layout;

use Domain\App\Http\Controllers\API\Inamap\InamapFilter;

class LayoutFilter extends InamapFilter
{
    protected string $searchColumn = 'name';

    public function level($level)
    {
        return $this->where('level', (int) $level);
    }
}
