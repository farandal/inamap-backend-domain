<?php

namespace Domain\App\Services\Inamap\Routing;

use Domain\App\Models\Inamap\Inamap;
use Domain\App\Models\Inamap\Location;
use Domain\App\Models\Inamap\Structure;
use Domain\App\Models\Inamap\Zone;

/**
 * Traduce una referencia `tipo:uuid` (ubicación, estructura o zona) del inamap
 * al extremo que entiende RouteEngine. Nunca resuelve elementos de otro inamap.
 */
class EndpointResolver
{
    public const TYPES = [
        'location'  => Location::class,
        'structure' => Structure::class,
        'zone'      => Zone::class,
    ];

    /** @return array{type: string, id: string, label: string, code: ?string, layout_id: string, point?: array, polygon?: array} */
    public function resolve(Inamap $inamap, string $reference): array
    {
        [$type, $id] = array_pad(explode(':', $reference, 2), 2, null);
        $class = self::TYPES[$type] ?? null;

        if (!$class || !$id || !\Illuminate\Support\Str::isUuid($id)) {
            throw new RouteException(RouteException::ENDPOINT_NOT_FOUND, "Referencia inválida: {$reference}");
        }

        $model = $class::query()->where('inamap_id', $inamap->id)->find($id);
        if (!$model) {
            throw new RouteException(RouteException::ENDPOINT_NOT_FOUND, "No existe {$reference} en este inamap");
        }

        $endpoint = [
            'type'      => $type,
            'id'        => $model->id,
            'label'     => $model->label,
            'code'      => $model->code,
            'layout_id' => $model->layout_id,
        ];

        if ($model instanceof Location) {
            $endpoint['point'] = [$model->x, $model->y];
        } else {
            $endpoint['polygon'] = $model->polygon ?? [];
        }

        return $endpoint;
    }
}
