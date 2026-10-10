<?php

namespace Domain\App\Http\Controllers\API\Inamap\Category;

use Domain\App\Http\Controllers\API\Inamap\InamapFilter;

class CategoryFilter extends InamapFilter
{
    protected string $searchColumn = 'name';

    public function name($name)
    {
        return $this->where('name', 'ILIKE', "%{$name}%");
    }

    public function categoryId($id)
    {
        return $this->where('category_id', $id);
    }

    /** `flat` lo interpreta el controlador; aquí solo se ignora. */
    public function flat($value)
    {
        return $this;
    }
}
