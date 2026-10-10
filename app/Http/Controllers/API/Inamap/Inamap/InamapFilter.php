<?php

namespace Domain\App\Http\Controllers\API\Inamap\Inamap;

use Domain\App\Http\Controllers\API\Inamap\InamapFilter as BaseFilter;

class InamapFilter extends BaseFilter
{
    protected string $searchColumn = 'name';

    public function name($name)
    {
        return $this->where('name', 'ILIKE', "%{$name}%");
    }

    public function status($status)
    {
        return $this->where('status', $status);
    }

    public function isActive($value)
    {
        return $this->where('is_active', filter_var($value, FILTER_VALIDATE_BOOLEAN));
    }
}
