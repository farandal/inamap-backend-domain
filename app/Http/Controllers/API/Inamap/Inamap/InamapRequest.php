<?php

namespace Domain\App\Http\Controllers\API\Inamap\Inamap;

use Domain\App\Http\Controllers\API\Inamap\InamapRequest as BaseRequest;
use Domain\App\Services\Inamap\Geometry\UnitRegistry;
use Illuminate\Validation\Rule;

class InamapRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'tenant_id'                    => 'nullable|uuid',
            'name'                         => $this->req() . '|string|max:255',
            'slug'                         => 'nullable|string|max:120',
            'description'                  => 'nullable|string|max:5000',
            'unit'                         => ['sometimes', 'string', Rule::in(app(UnitRegistry::class)->enabled())],
            'is_active'                    => 'sometimes|boolean',
            'settings'                     => 'sometimes|nullable|array',
            'settings.access_mode'         => ['sometimes', Rule::in(config('inamap.access_modes'))],
            'settings.session_ttl_minutes' => 'sometimes|integer|min:1|max:' . config('inamap.route_sessions.max_ttl_minutes'),
            'settings.initial_layout_id'   => 'sometimes|nullable|uuid',
            'settings.initial_zoom'        => 'sometimes|nullable|numeric|min:0.05|max:50',
            'settings.primary_color'       => 'sometimes|nullable|string|max:16',
            'settings.allow_zone_overlap'  => 'sometimes|boolean',
        ];
    }
}
