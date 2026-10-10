<?php

namespace Domain\App\Http\Controllers\API\Inamap\Structure;

use Domain\App\Http\Controllers\API\Inamap\InamapRequest;

class StructureRequest extends InamapRequest
{
    public function rules(): array
    {
        return [
            'layout_id' => $this->isCreate() ? 'required|uuid' : 'sometimes|uuid',
            'code'      => 'nullable|string|max:64',
            'label'     => $this->req() . '|string|max:255',
            'color'     => 'nullable|string|max:16',
            ...$this->polygonRules(),
            ...$this->searchableRules(),
        ];
    }
}
