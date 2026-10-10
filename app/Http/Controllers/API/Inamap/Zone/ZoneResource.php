<?php

namespace Domain\App\Http\Controllers\API\Inamap\Zone;

use Illuminate\Http\Resources\Json\JsonResource;

class ZoneResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'         => $this->id,
            'inamap_id'  => $this->inamap_id,
            'layout_id'  => $this->layout_id,
            'code'       => $this->code,
            'label'      => $this->label,
            'color'      => $this->color,
            'polygon'    => $this->polygon,
            'terms'      => $this->terms ?? [],
            'metadata'   => $this->metadata ?? (object) [],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
