<?php

namespace Domain\App\Http\Controllers\API\Inamap\Location;

use Domain\App\Http\Controllers\API\Inamap\InamapRequest;

class LocationRequest extends InamapRequest
{
    public function rules(): array
    {
        return [
            'layout_id'    => $this->isCreate() ? 'required|uuid' : 'sometimes|uuid',
            'structure_id' => 'nullable|uuid',
            'category_id'  => 'nullable|uuid',
            'code'         => 'nullable|string|max:64',
            'label'        => $this->req() . '|string|max:255',
            'x'            => $this->req() . '|numeric',
            'y'            => $this->req() . '|numeric',
            'icon'         => 'nullable|string|max:64',
            'qr_url'       => 'nullable|url|max:2048',
            ...$this->searchableRules(),
        ];
    }
}
