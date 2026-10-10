<?php

namespace Domain\App\Http\Controllers\API\Inamap\Category;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                => $this->id,
            'tenant_id'         => $this->tenant_id,
            'tenancy_id'        => $this->tenancy_id,
            'category_id'       => $this->category_id,
            'name'              => $this->name,
            'breadcrumbed_name' => $this->when($request->boolean('flat'), fn () => $this->breadcrumbed_name),
            'color'             => $this->color,
            'icon'              => $this->icon,
            'image_url'         => $this->image_url,
            'tree_index'        => $this->tree_index,
            'is_tenancy_wide'   => $this->tenant_id === null,
            'category'          => $this->whenLoaded('category', fn () => $this->category ? ['id' => $this->category->id, 'name' => $this->category->name] : null),
            'subcategories'     => CategoryResource::collection($this->whenLoaded('subcategoriesTree')),
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];
    }
}
