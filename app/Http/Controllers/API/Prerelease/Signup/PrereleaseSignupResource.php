<?php

namespace Domain\App\Http\Controllers\API\Prerelease\Signup;

use Illuminate\Http\Resources\Json\JsonResource;

class PrereleaseSignupResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id'                 => $this->id,
            'name'               => $this->name,
            'email'              => $this->email,
            'contact_phone'      => $this->contact_phone,
            'business_website'   => $this->business_website,
            'business_instagram' => $this->business_instagram,
            'preferred_language' => $this->preferred_language,
            'created_at'         => $this->created_at,
            'updated_at'         => $this->updated_at,
        ];
    }
}
