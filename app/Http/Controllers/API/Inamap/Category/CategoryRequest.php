<?php

namespace Domain\App\Http\Controllers\API\Inamap\Category;

use Domain\App\Http\Controllers\API\Inamap\InamapRequest;

class CategoryRequest extends InamapRequest
{
    public function rules(): array
    {
        return [
            'tenant_id'   => 'nullable|uuid',
            'category_id' => 'nullable|uuid',
            'name'        => $this->req() . '|string|max:255',
            'color'       => 'nullable|string|max:16',
            'icon'        => 'nullable|string|max:64',
            'tree_index'  => 'sometimes|integer',
            'image'       => 'sometimes|nullable|file|mimes:jpg,jpeg,png,webp,svg|max:5120',
        ];
    }
}
