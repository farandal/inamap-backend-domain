<?php

namespace Domain\App\Http\Controllers\API\Inamap\PathLink;

use Illuminate\Http\Resources\Json\JsonResource;

class PathLinkResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'            => $this->id,
            'inamap_id'     => $this->inamap_id,
            'from_path_id'  => $this->from_path_id,
            'from_end'      => $this->from_end,
            'to_path_id'    => $this->to_path_id,
            'to_end'        => $this->to_end,
            'kind'          => $this->kind,
            'vertical_type' => $this->vertical_type,
            'is_accessible' => $this->is_accessible,
            'cost'          => $this->cost,
            'bidirectional' => $this->bidirectional,
            'metadata'      => $this->metadata ?? (object) [],
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
        ];
    }
}
