<?php

namespace Domain\App\Http\Controllers\API\Inamap\Location;

use Illuminate\Http\Resources\Json\JsonResource;

class LocationResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'           => $this->id,
            'inamap_id'    => $this->inamap_id,
            'layout_id'    => $this->layout_id,
            'structure_id' => $this->structure_id,
            'category_id'  => $this->category_id,
            'category'     => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id, 'name' => $this->category->name, 'color' => $this->category->color, 'icon' => $this->category->icon,
            ] : null),
            'code'         => $this->code,
            'label'        => $this->label,
            'x'            => $this->x,
            'y'            => $this->y,
            'icon'         => $this->icon,
            'qr_url'       => $this->qr_url,
            'terms'        => $this->terms ?? [],
            'metadata'     => $this->metadata ?? (object) [],
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
        ];
    }
}
