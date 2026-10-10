<?php

namespace Domain\App\Services\Inamap\Diagnostics;

use Domain\App\Models\Inamap\Inamap;
use Domain\App\Models\Inamap\Location;
use Domain\App\Services\Inamap\Routing\Graph;
use Domain\App\Services\Inamap\Routing\InamapGraphRepository;
use Domain\App\Services\Inamap\Routing\RouteEngine;

/**
 * Diagnóstico de conectividad (RF-18): ubicaciones que no tienen camino cerca
 * o que quedan fuera de la red principal, y caminos aislados.
 *
 * La "red principal" es la componente conexa más grande (ignorando sentidos).
 * Con `accessible`, los enlaces no accesibles se descartan, de modo que se ve
 * qué queda aislado para una persona en silla de ruedas.
 */
class InamapConnectivityService
{
    public function __construct(
        private readonly InamapGraphRepository $graphs,
        private readonly RouteEngine $engine,
    ) {
    }

    public function diagnose(Inamap $inamap, bool $accessible = false): array
    {
        $graph = $this->graphs->build($inamap);
        $components = $this->components($graph, $accessible);
        $sizes = array_count_values($components);
        arsort($sizes);
        $main = array_key_first($sizes);
        $tolerance = $this->graphs->tolerance($inamap);

        $unreachable = [];
        foreach (Location::query()->where('inamap_id', $inamap->id)->get() as $location) {
            $anchors = $this->engine->anchorNodes($graph, [
                'type' => 'location', 'layout_id' => $location->layout_id, 'point' => [$location->x, $location->y],
            ], $tolerance);

            $reason = match (true) {
                !$anchors => 'NO_PATH_NEAR_ENDPOINT',
                !array_filter($anchors, fn ($node) => ($components[$node] ?? null) === $main) => 'DISCONNECTED',
                default => null,
            };

            if ($reason) {
                $unreachable[] = ['id' => $location->id, 'label' => $location->label, 'layout_id' => $location->layout_id, 'reason' => $reason];
            }
        }

        $isolatedPaths = [];
        foreach ($graph->pathNodes as $pathId => $nodes) {
            if (($components[$nodes[0]] ?? null) !== $main) {
                $isolatedPaths[] = $pathId;
            }
        }

        return [
            'accessible'            => $accessible,
            'nodes'                 => count($graph->nodes),
            'components'            => count($sizes),
            'unreachable_locations' => $unreachable,
            'isolated_path_ids'     => $isolatedPaths,
            'ok'                    => !$unreachable && !$isolatedPaths,
        ];
    }

    /** @return array<string, int> nodo => componente */
    private function components(Graph $graph, bool $accessible): array
    {
        $neighbors = [];
        foreach ($graph->adjacency as $from => $edges) {
            foreach ($edges as $edge) {
                if ($accessible && !($edge['accessible'] ?? true)) {
                    continue;
                }
                $neighbors[$from][] = $edge['to'];
                $neighbors[$edge['to']][] = $from;
            }
        }

        $component = [];
        $index = 0;
        foreach (array_keys($graph->nodes) as $start) {
            $start = (string) $start;
            if (isset($component[$start])) {
                continue;
            }
            $stack = [$start];
            $component[$start] = $index;
            while ($stack) {
                $node = array_pop($stack);
                foreach ($neighbors[$node] ?? [] as $next) {
                    if (!isset($component[$next])) {
                        $component[$next] = $index;
                        $stack[] = $next;
                    }
                }
            }
            $index++;
        }

        return $component;
    }
}
