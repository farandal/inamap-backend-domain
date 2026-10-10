<?php

namespace Domain\App\Http\Controllers\API\Inamap\Path;

use Illuminate\Http\Resources\Json\JsonResource;

class PathResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'         => $this->id,
            'inamap_id'  => $this->inamap_id,
            'layout_id'  => $this->layout_id,
            'label'      => $this->label,
            'points'     => $this->points,
            'direction'  => $this->direction,
            'metadata'   => $this->metadata ?? (object) [],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
