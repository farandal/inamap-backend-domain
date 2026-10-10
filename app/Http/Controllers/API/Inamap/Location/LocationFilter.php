<?php

namespace Domain\App\Http\Controllers\API\Inamap\Location;

use Domain\App\Http\Controllers\API\Inamap\InamapFilter;

class LocationFilter extends InamapFilter
{
    public function categoryId($id)
    {
        return $this->where('category_id', $id);
    }

    public function structureId($id)
    {
        return $this->where('structure_id', $id);
    }
}
