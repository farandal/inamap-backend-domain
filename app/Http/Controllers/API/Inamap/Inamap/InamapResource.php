<?php

namespace Domain\App\Http\Controllers\API\Inamap\Inamap;

use Domain\App\Http\Controllers\API\Inamap\Layout\LayoutResource;
use Illuminate\Http\Resources\Json\JsonResource;

class InamapResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'           => $this->id,
            'tenant_id'    => $this->tenant_id,
            'tenancy_id'   => $this->tenancy_id,
            'name'         => $this->name,
            'slug'         => $this->slug,
            'description'  => $this->description,
            'unit'         => $this->unit,
            'status'       => $this->status,
            'published_at' => $this->published_at,
            'is_active'    => $this->is_active,
            'settings'     => $this->publicSettings(),
            'public_url'   => rtrim((string) config('inamap.route_sessions.app_url'), '/') . "/inamap/{$this->id}",
            'layouts'      => LayoutResource::collection($this->whenLoaded('layouts')),
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
        ];
    }
}
