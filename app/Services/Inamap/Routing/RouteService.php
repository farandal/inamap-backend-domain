<?php

namespace Domain\App\Services\Inamap\Routing;

use App\Models\Tenant;
use Domain\App\Models\Inamap\Inamap;
use Domain\App\Models\Inamap\Layout;
use Domain\App\Services\Inamap\Geometry\UnitRegistry;

/**
 * Fachada de rutas para controladores y sesiones: resuelve extremos, carga el
 * grafo cacheado, corre RouteEngine y arma la respuesta pública (README §9.2).
 */
class RouteService
{
    public function __construct(
        private readonly InamapGraphRepository $graphs,
        private readonly EndpointResolver $endpoints,
        private readonly RouteEngine $engine,
        private readonly UnitRegistry $units,
    ) {
    }

    /** @throws RouteException */
    public function route(Inamap $inamap, string $fromRef, string $toRef, bool $accessible = false): array
    {
        $from = $this->endpoints->resolve($inamap, $fromRef);
        $to = $this->endpoints->resolve($inamap, $toRef);

        $result = $this->engine->route(
            $this->graphs->for($inamap),
            $from,
            $to,
            $accessible,
            $this->graphs->tolerance($inamap),
        );

        $layoutNames = Layout::query()->where('inamap_id', $inamap->id)->pluck('name', 'id');
        foreach ($result['steps'] as &$step) {
            $step['layout_name'] = $layoutNames[$step['layout_id']] ?? null;
        }
        unset($step);

        $toMeters = $this->units->toMeters($inamap->unit);
        $speed = $this->walkingSpeed($inamap);

        return [
            'inamap_id'         => $inamap->id,
            'unit'              => $inamap->unit,
            'accessible'        => $accessible,
            'distance'          => $result['distance'],
            // El costo incluye penalizaciones de enlaces verticales expresadas en metros equivalentes.
            'estimated_seconds' => (int) ceil(($result['cost'] * $toMeters) / $speed),
            'walking_speed_mps' => $speed,
            'from'              => $this->describe($from),
            'to'                => $this->describe($to),
            'steps'             => $result['steps'],
        ];
    }

    /** Velocidad peatonal del tenant (S3), con el valor de plataforma como respaldo. */
    public function walkingSpeed(Inamap $inamap): float
    {
        $settings = Tenant::query()->whereKey($inamap->tenant_id)->first()?->settings ?? [];
        $speed = (float) ($settings['inamap_walking_speed_mps'] ?? 0);

        return $speed > 0 ? $speed : (float) config('inamap.default_walking_speed_mps', 1.2);
    }

    private function describe(array $endpoint): array
    {
        return [
            'type'      => $endpoint['type'],
            'id'        => $endpoint['id'],
            'label'     => $endpoint['label'],
            'code'      => $endpoint['code'],
            'layout_id' => $endpoint['layout_id'],
        ];
    }
}
