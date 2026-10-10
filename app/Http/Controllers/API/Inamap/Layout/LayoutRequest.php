<?php

namespace Domain\App\Http\Controllers\API\Inamap\Layout;

use Domain\App\Http\Controllers\API\Inamap\InamapRequest;

class LayoutRequest extends InamapRequest
{
    public function rules(): array
    {
        return [
            'inamap_id' => $this->isCreate() ? 'required|uuid' : 'sometimes|uuid',
            'name'      => $this->req() . '|string|max:255',
            'level'     => 'sometimes|integer|min:-100|max:500',
            'sort'      => 'sometimes|integer',
            'width'     => $this->req() . '|numeric|gt:0|max:100000',
            'height'    => $this->req() . '|numeric|gt:0|max:100000',
            'image'     => 'sometimes|nullable|file|mimes:jpg,jpeg,png,webp,svg|max:20480',
        ];
    }
}
