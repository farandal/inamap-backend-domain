<?php

namespace Domain\App\Http\Controllers\API\Inamap\PathLink;

use Domain\App\Http\Controllers\API\Inamap\InamapRequest;
use Domain\App\Models\Inamap\PathLink;
use Illuminate\Validation\Rule;

class PathLinkRequest extends InamapRequest
{
    public function rules(): array
    {
        return [
            'from_path_id'  => $this->req() . '|uuid',
            'from_end'      => [...explode('|', $this->req()), Rule::in(PathLink::ENDS)],
            'to_path_id'    => $this->req() . '|uuid',
            'to_end'        => [...explode('|', $this->req()), Rule::in(PathLink::ENDS)],
            'kind'          => ['sometimes', Rule::in(PathLink::KINDS)],
            'vertical_type' => ['nullable', 'required_if:kind,vertical', Rule::in(array_keys(config('inamap.vertical_types')))],
            'cost'          => 'nullable|numeric|min:0|max:100000',
            'bidirectional' => 'sometimes|boolean',
            'metadata'      => 'nullable|array',
        ];
    }
}
