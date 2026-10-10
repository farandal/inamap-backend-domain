<?php

namespace Domain\App\Http\Controllers\API\Inamap\Layout;

use Illuminate\Http\Resources\Json\JsonResource;

class LayoutResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'         => $this->id,
            'inamap_id'  => $this->inamap_id,
            'tenant_id'  => $this->tenant_id,
            'name'       => $this->name,
            'level'      => $this->level,
            'sort'       => $this->sort,
            'width'      => $this->width,
            'height'     => $this->height,
            'image_url'  => $this->image_url,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
