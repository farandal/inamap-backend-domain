<?php

namespace Domain\App\Services\Inamap\Routing;

use Domain\App\Services\Inamap\Geometry\PolygonService;

/**
 * Construye el grafo de rutas de un inamap (README §3.5).
 *
 * 1. Cada vértice de cada camino es un nodo; vértices a menos de la tolerancia
 *    (aunque sean de caminos distintos) se fusionan en un solo nodo.
 * 2. El extremo de un camino que cae sobre el tramo interior de otro camino
 *    (unión en "T") divide ese tramo, para que se conecten sin enlace explícito.
 * 3. Aristas entre vértices consecutivos según `direction`.
 * 4. Enlaces explícitos: `link`/`vertex` (misma planta) y `vertical` (entre
 *    layouts, con tipo y penalización).
 *
 * PHP puro: recibe arreglos y la configuración ya resuelta.
 */
class PathGraphBuilder
{
    public function __construct(
        private readonly PolygonService $geometry = new PolygonService(),
    ) {
    }

    /**
     * @param list<array{id: string, layout_id: string, points: array, direction?: string}> $paths
     * @param list<array{id?: string, from_path_id: string, from_end: string, to_path_id: string, to_end: string, kind?: string, vertical_type?: ?string, cost?: ?float, bidirectional?: bool}> $links
     * @param array{tolerance?: float, to_meters?: float, vertical_types?: array} $options
     *        tolerance en la unidad del inamap; vertical_types como config('inamap.vertical_types').
     */
    public function build(array $paths, array $links, array $options = []): Graph
    {
        $tolerance = max(0.0, (float) ($options['tolerance'] ?? 0.3));
        $toMeters = (float) ($options['to_meters'] ?? 1.0) ?: 1.0;
        $verticalTypes = $options['vertical_types'] ?? [];

        $graph = new Graph();
        $buckets = [];
        $sequences = [];
        $directions = [];
        $layouts = [];

        // 1. Nodos con fusión por tolerancia (hash espacial por layout).
        foreach ($paths as $path) {
            $points = $this->geometry->normalize($path['points'] ?? []);
            if (count($points) < 2) {
                continue;
            }

            $pathId = (string) $path['id'];
            $layoutId = (string) $path['layout_id'];
            $layouts[$pathId] = $layoutId;
            $directions[$pathId] = $path['direction'] ?? 'both';

            $sequence = [];
            foreach ($points as $point) {
                $nodeId = $this->findOrCreateNode($graph, $buckets, $layoutId, $point, $tolerance);
                if (end($sequence) !== $nodeId) {
                    $sequence[] = $nodeId;
                }
            }

            if (count($sequence) >= 2) {
                $sequences[$pathId] = $sequence;
            }
        }

        // 2. Uniones en "T": un extremo sobre el interior de un tramo de otro camino.
        $sequences = $this->splitAtJunctions($graph, $sequences, $layouts, max($tolerance, 1e-6));

        // 3. Aristas de cada camino.
        foreach ($sequences as $pathId => $sequence) {
            $direction = $directions[$pathId];
            $forward = $direction !== 'backward';
            $backward = $direction !== 'forward';

            for ($i = 0, $n = count($sequence) - 1; $i < $n; $i++) {
                $a = $sequence[$i];
                $b = $sequence[$i + 1];
                $length = $this->geometry->distance($graph->point($a), $graph->point($b));

                if ($forward) {
                    $graph->addEdge($a, $b, $length, $length);
                }
                if ($backward) {
                    $graph->addEdge($b, $a, $length, $length);
                }

                $graph->segments[] = [
                    'layout_id' => $layouts[$pathId],
                    'path_id'   => $pathId,
                    'a'         => $a,
                    'b'         => $b,
                    'forward'   => $forward,
                    'backward'  => $backward,
                ];
            }

            $graph->pathNodes[$pathId] = $sequence;
        }

        // 4. Enlaces explícitos.
        foreach ($links as $link) {
            $from = $this->endNode($sequences, (string) $link['from_path_id'], $link['from_end'] ?? 'end');
            $to = $this->endNode($sequences, (string) $link['to_path_id'], $link['to_end'] ?? 'start');

            if ($from === null || $to === null) {
                continue;
            }

            $kind = $link['kind'] ?? 'link';
            $bidirectional = (bool) ($link['bidirectional'] ?? true);

            if ($kind === 'vertical') {
                $type = $link['vertical_type'] ?? null;
                $typeConfig = $verticalTypes[$type] ?? ['accessible' => false, 'default_cost_m' => 10.0];
                $costMeters = isset($link['cost']) && $link['cost'] !== null
                    ? (float) $link['cost']
                    : (float) ($typeConfig['default_cost_m'] ?? 10.0);
                $weight = $costMeters / $toMeters;
                $accessible = (bool) ($typeConfig['accessible'] ?? false);

                $graph->addEdge($from, $to, $weight, 0.0, 'vertical', $type, $accessible);
                if ($bidirectional) {
                    $graph->addEdge($to, $from, $weight, 0.0, 'vertical', $type, $accessible);
                }

                continue;
            }

            $length = $graph->layoutOf($from) === $graph->layoutOf($to)
                ? $this->geometry->distance($graph->point($from), $graph->point($to))
                : 0.0;
            $weight = isset($link['cost']) && $link['cost'] !== null ? ((float) $link['cost']) / $toMeters : $length;

            $graph->addEdge($from, $to, $weight, $length, 'link');
            if ($bidirectional) {
                $graph->addEdge($to, $from, $weight, $length, 'link');
            }
        }

        return $graph;
    }

    private function findOrCreateNode(Graph $graph, array &$buckets, string $layoutId, array $point, float $tolerance): string
    {
        $cell = $tolerance > 0 ? $tolerance : 1e-6;
        $cx = (int) floor($point[0] / $cell);
        $cy = (int) floor($point[1] / $cell);

        for ($dx = -1; $dx <= 1; $dx++) {
            for ($dy = -1; $dy <= 1; $dy++) {
                foreach ($buckets[$layoutId][$cx + $dx][$cy + $dy] ?? [] as $candidate) {
                    if ($this->geometry->distance($graph->point($candidate), $point) <= $tolerance + PolygonService::EPSILON) {
                        return $candidate;
                    }
                }
            }
        }

        $nodeId = 'n' . count($graph->nodes);
        $graph->addNode($nodeId, $layoutId, round($point[0], 4), round($point[1], 4));
        $buckets[$layoutId][$cx][$cy][] = $nodeId;

        return $nodeId;
    }

    private function splitAtJunctions(Graph $graph, array $sequences, array $layouts, float $tolerance): array
    {
        $endpoints = [];
        foreach ($sequences as $pathId => $sequence) {
            $endpoints[$sequence[0]] = $layouts[$pathId];
            $endpoints[$sequence[count($sequence) - 1]] = $layouts[$pathId];
        }

        foreach ($sequences as $pathId => $sequence) {
            $result = [$sequence[0]];

            for ($i = 0, $n = count($sequence) - 1; $i < $n; $i++) {
                $a = $sequence[$i];
                $b = $sequence[$i + 1];
                $inserts = [];

                foreach ($endpoints as $node => $layoutId) {
                    if ($layoutId !== $layouts[$pathId] || $node === $a || $node === $b) {
                        continue;
                    }

                    [$projected, $t] = $this->geometry->projectOnSegment($graph->point($node), $graph->point($a), $graph->point($b));
                    if ($t > 0 && $t < 1 && $this->geometry->distance($projected, $graph->point($node)) <= $tolerance) {
                        $inserts[$node] = $t;
                    }
                }

                asort($inserts);
                foreach (array_keys($inserts) as $node) {
                    $result[] = (string) $node;
                }
                $result[] = $b;
            }

            $sequences[$pathId] = $result;
        }

        return $sequences;
    }

    private function endNode(array $sequences, string $pathId, string $end): ?string
    {
        $sequence = $sequences[$pathId] ?? null;
        if (!$sequence) {
            return null;
        }

        return $end === 'start' ? $sequence[0] : $sequence[count($sequence) - 1];
    }
}
