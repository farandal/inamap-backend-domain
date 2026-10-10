<?php

namespace Domain\App\Http\Controllers\API\Inamap\PathLink;

use Domain\App\Http\Controllers\API\Inamap\InamapFilter;

class PathLinkFilter extends InamapFilter
{
    public function q($value)
    {
        return $this->where('vertical_type', 'ILIKE', '%' . trim((string) $value) . '%');
    }

    public function kind($kind)
    {
        return $this->where('kind', $kind);
    }

    public function pathId($id)
    {
        return $this->where(fn ($q) => $q->where('from_path_id', $id)->orWhere('to_path_id', $id));
    }
}
