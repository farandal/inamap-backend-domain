<?php

namespace Domain\App\Http\Controllers\API\Inamap\Path;

use Domain\App\Http\Controllers\API\Inamap\InamapRequest;
use Domain\App\Models\Inamap\Path;
use Illuminate\Validation\Rule;

class PathRequest extends InamapRequest
{
    public function rules(): array
    {
        return [
            'layout_id'  => $this->isCreate() ? 'required|uuid' : 'sometimes|uuid',
            'label'      => 'nullable|string|max:255',
            'points'     => [...explode('|', $this->req()), 'array', 'min:2', 'max:5000'],
            'points.*'   => 'array|size:2',
            'points.*.*' => 'numeric',
            'direction'  => ['sometimes', Rule::in(Path::DIRECTIONS)],
            'metadata'   => 'nullable|array',
        ];
    }
}
