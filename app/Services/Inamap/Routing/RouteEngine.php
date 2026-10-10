<?php

namespace Domain\App\Services\Inamap\Routing;

use Domain\App\Services\Inamap\Geometry\PolygonService;
use SplPriorityQueue;

/**
 * Cálculo de rutas sobre un Graph (README §3.5). PHP puro.
 *
 * Un extremo (origen o destino) es uno de:
 *   ['type' => 'location',             'layout_id' => ..., 'point'   => [x, y]]
 *   ['type' => 'zone' | 'structure',   'layout_id' => ..., 'polygon' => [[x, y], ...]]
 *
 * Se agrega un súper-origen S y un súper-destino T conectados a los nodos que
 * representan cada extremo, y se corre Dijkstra de S a T. Así una zona o
 * estructura es naturalmente multi-fuente / multi-destino.
 */
class RouteEngine
{
    private const SOURCE = '__S';
    private const TARGET = '__T';

    /**
     * Máximo de tramos sobre los que se proyecta una ubicación. Solo se usan
     * los que empatan con el más cercano (± tolerancia): proyectar sobre un
     * tramo más lejano permitiría "caminar fuera del camino" y saltar entre
     * caminos no conectados.
     */
    private const SNAP_CANDIDATES = 4;

    public function __construct(
        private readonly PolygonService $geometry = new PolygonService(),
    ) {
    }

    /**
     * @return array{distance: float, cost: float, steps: list<array>}
     *
     * @throws RouteException
     */
    public function route(Graph $graph, array $from, array $to, bool $accessible = false, float $tolerance = 0.3): array
    {
        $result = $this->search($graph, $from, $to, $accessible, $tolerance);

        if ($result === null) {
            if ($accessible && $this->search($graph, $from, $to, false, $tolerance) !== null) {
                throw new RouteException(RouteException::UNREACHABLE_ACCESSIBLE);
            }

            throw new RouteException(RouteException::UNREACHABLE);
        }

        return $result;
    }

    /**
     * Nodos reales del grafo que representan un extremo (los dos extremos del
     * tramo cuando el ancla es un punto proyectado). Usado por el diagnóstico
     * de conectividad.
     *
     * @return list<string>
     */
    public function anchorNodes(Graph $graph, array $endpoint, float $tolerance = 0.3): array
    {
        $extra = [];
        $virtual = [];
        $nodes = [];

        foreach ($this->attach($graph, $extra, $endpoint, 'probe', true, $tolerance, $virtual) as $anchor) {
            $node = $anchor['node'];
            if (isset($virtual[$node])) {
                $segment = $graph->segments[$virtual[$node]['segment']];
                $nodes[$segment['a']] = true;
                $nodes[$segment['b']] = true;
            } elseif (isset($graph->nodes[$node])) {
                $nodes[$node] = true;
            }
        }

        return array_map('strval', array_keys($nodes));
    }

    private function search(Graph $graph, array $from, array $to, bool $accessible, float $tolerance): ?array
    {
        /** @var array<string, list<array>> $extra Aristas y nodos virtuales sin tocar el grafo cacheado */
        $extra = [];
        $virtual = [];

        $sources = $this->attach($graph, $extra, $from, 'src', true, $tolerance, $virtual);
        $targets = $this->attach($graph, $extra, $to, 'dst', false, $tolerance, $virtual);

        if (!$sources || !$targets) {
            throw new RouteException(RouteException::NO_PATH_NEAR_ENDPOINT);
        }

        // Origen y destino proyectados sobre el mismo tramo: recorrido directo.
        $this->connectSameSegment($graph, $extra, $virtual, $sources, $targets);

        foreach ($sources as $source) {
            $extra[self::SOURCE][] = $this->edge($source['node'], $source['length'], $source['length']);
        }
        foreach ($targets as $target) {
            $extra[$target['node']][] = $this->edge(self::TARGET, $target['length'], $target['length']);
        }

        // Dijkstra.
        $dist = [self::SOURCE => 0.0];
        $prev = [];
        $visited = [];
        $queue = new SplPriorityQueue();
        $queue->setExtractFlags(SplPriorityQueue::EXTR_BOTH);
        $queue->insert(self::SOURCE, 0.0);

        while (!$queue->isEmpty()) {
            ['data' => $node, 'priority' => $priority] = $queue->extract();
            if (isset($visited[$node])) {
                continue;
            }
            $visited[$node] = true;

            if ($node === self::TARGET) {
                break;
            }

            $edges = array_merge($graph->adjacency[$node] ?? [], $extra[$node] ?? []);
            foreach ($edges as $edge) {
                if ($accessible && !($edge['accessible'] ?? true)) {
                    continue;
                }

                $candidate = $dist[$node] + $edge['weight'];
                if ($candidate < ($dist[$edge['to']] ?? INF) - 1e-12) {
                    $dist[$edge['to']] = $candidate;
                    $prev[$edge['to']] = [$node, $edge];
                    $queue->insert($edge['to'], -$candidate);
                }
            }
        }

        if (!isset($dist[self::TARGET])) {
            return null;
        }

        // Reconstrucción S → T como lista de aristas.
        $chain = [];
        for ($node = self::TARGET; isset($prev[$node]); $node = $prev[$node][0]) {
            array_unshift($chain, [$prev[$node][0], $prev[$node][1]]);
        }

        return $this->buildSteps($graph, $virtual, $chain, $from, $to, $dist[self::TARGET]);
    }

    /**
     * Conecta un extremo al grafo. Devuelve los nodos ancla con la distancia
     * fuera de camino (de la ubicación/polígono al ancla).
     */
    private function attach(Graph $graph, array &$extra, array $endpoint, string $prefix, bool $isSource, float $tolerance, array &$virtual = []): array
    {
        $layoutId = (string) ($endpoint['layout_id'] ?? '');

        if (($endpoint['type'] ?? 'location') !== 'location') {
            $polygon = $this->geometry->normalize($endpoint['polygon'] ?? []);
            if (count($polygon) < 3) {
                return [];
            }

            // Se llega a un polígono por su borde (HU-VIS-07): cada cruce de
            // un tramo con el borde es un ancla; los nodos interiores también
            // (caminos que nacen o mueren dentro).
            $anchors = [];
            foreach ($graph->nodes as $nodeId => $node) {
                if ($node['layout_id'] !== $layoutId) {
                    continue;
                }
                $point = [$node['x'], $node['y']];
                if ($this->geometry->contains($polygon, $point)
                    || $this->geometry->distanceToBoundary($polygon, $point) <= $tolerance) {
                    $anchors[] = ['node' => (string) $nodeId, 'length' => 0.0];
                }
            }

            $crossing = 0;
            foreach ($graph->segments as $index => $segment) {
                if ($segment['layout_id'] !== $layoutId) {
                    continue;
                }
                $a = $graph->point($segment['a']);
                $b = $graph->point($segment['b']);
                foreach ($this->geometry->boundaryCrossings($polygon, $a, $b) as [$point, $t]) {
                    if ($t <= 0.0 || $t >= 1.0) {
                        continue; // el extremo ya es un nodo ancla
                    }
                    $nodeId = "{$prefix}:x{$crossing}";
                    $crossing++;
                    $this->addVirtualOnSegment($graph, $extra, $virtual, $nodeId, $index, $point, $t, $isSource);
                    $anchors[] = ['node' => $nodeId, 'length' => 0.0];
                }
            }

            if ($anchors) {
                return $anchors;
            }

            // Ningún nodo dentro: se proyecta el centroide y se cobra solo el
            // tramo entre el borde del polígono y el camino.
            return $this->snapPoint($graph, $extra, $layoutId, $this->geometry->centroid($polygon), $prefix, $isSource, $virtual, $tolerance, $polygon);
        }

        return $this->snapPoint($graph, $extra, $layoutId, $endpoint['point'] ?? [0, 0], $prefix, $isSource, $virtual, $tolerance);
    }

    private function snapPoint(Graph $graph, array &$extra, string $layoutId, array $point, string $prefix, bool $isSource, array &$virtual, float $tolerance, ?array $polygon = null): array
    {
        $candidates = [];
        foreach ($graph->segments as $index => $segment) {
            if ($segment['layout_id'] !== $layoutId) {
                continue;
            }
            [$projected, $t] = $this->geometry->projectOnSegment($point, $graph->point($segment['a']), $graph->point($segment['b']));
            $candidates[] = ['index' => $index, 'point' => $projected, 't' => $t, 'distance' => $this->geometry->distance($point, $projected)];
        }

        if (!$candidates) {
            return [];
        }

        usort($candidates, fn ($x, $y) => $x['distance'] <=> $y['distance']);
        $nearest = $candidates[0]['distance'];
        $candidates = array_filter($candidates, fn ($c) => $c['distance'] <= $nearest + $tolerance);

        $anchors = [];
        foreach (array_slice($candidates, 0, self::SNAP_CANDIDATES) as $i => $candidate) {
            $nodeId = "{$prefix}:{$i}";
            $this->addVirtualOnSegment($graph, $extra, $virtual, $nodeId, $candidate['index'], $candidate['point'], $candidate['t'], $isSource);

            $offPath = $polygon !== null && !$this->geometry->contains($polygon, $candidate['point'])
                ? $this->geometry->distanceToBoundary($polygon, $candidate['point'])
                : ($polygon !== null ? 0.0 : $candidate['distance']);

            $anchors[] = ['node' => $nodeId, 'length' => $offPath];
        }

        return $anchors;
    }

    /** Nodo virtual sobre un tramo, con aristas que respetan su sentido. */
    private function addVirtualOnSegment(Graph $graph, array &$extra, array &$virtual, string $nodeId, int $segmentIndex, array $point, float $t, bool $isSource): void
    {
        $segment = $graph->segments[$segmentIndex];
        $toA = $this->geometry->distance($point, $graph->point($segment['a']));
        $toB = $this->geometry->distance($point, $graph->point($segment['b']));

        $virtual[$nodeId] = [
            'layout_id' => $segment['layout_id'],
            'point'     => $point,
            'segment'   => $segmentIndex,
            't'         => $t,
        ];

        if ($isSource) {
            if ($segment['forward']) {
                $extra[$nodeId][] = $this->edge($segment['b'], $toB, $toB);
            }
            if ($segment['backward']) {
                $extra[$nodeId][] = $this->edge($segment['a'], $toA, $toA);
            }

            return;
        }

        if ($segment['forward']) {
            $extra[$segment['a']][] = $this->edge($nodeId, $toA, $toA);
        }
        if ($segment['backward']) {
            $extra[$segment['b']][] = $this->edge($nodeId, $toB, $toB);
        }
    }

    private function connectSameSegment(Graph $graph, array &$extra, array $virtual, array $sources, array $targets): void
    {
        foreach ($sources as $source) {
            foreach ($targets as $target) {
                $s = $virtual[$source['node']] ?? null;
                $t = $virtual[$target['node']] ?? null;
                if (!$s || !$t || $s['segment'] !== $t['segment']) {
                    continue;
                }

                // Avanzar en el tramo (a → b) exige forward; retroceder exige backward.
                $segment = $graph->segments[$s['segment']];
                $allowed = $s['t'] <= $t['t'] ? $segment['forward'] : $segment['backward'];
                if (!$allowed) {
                    continue;
                }

                $length = $this->geometry->distance($s['point'], $t['point']);
                $extra[$source['node']][] = $this->edge($target['node'], $length, $length);
            }
        }
    }

    private function edge(string $to, float $weight, float $length, string $kind = 'segment'): array
    {
        return [
            'to'            => $to,
            'weight'        => $weight,
            'length'        => $length,
            'kind'          => $kind,
            'vertical_type' => null,
            'accessible'    => true,
        ];
    }

    private function buildSteps(Graph $graph, array $virtual, array $chain, array $from, array $to, float $cost): array
    {
        $steps = [];
        $current = null;
        $distance = 0.0;

        $pointOf = function (string $node) use ($graph, $virtual) {
            if (isset($virtual[$node])) {
                return $virtual[$node]['point'];
            }

            return isset($graph->nodes[$node]) ? $graph->point($node) : null;
        };
        $layoutOf = fn (string $node) => $virtual[$node]['layout_id'] ?? $graph->layoutOf($node);

        $open = function (string $layoutId) use (&$current) {
            $current = ['layout_id' => $layoutId, 'points' => [], 'distance' => 0.0, 'transition' => null];
        };
        $push = function (?array $point) use (&$current) {
            if ($point === null) {
                return;
            }
            $point = [round($point[0], 4), round($point[1], 4)];
            if (end($current['points']) !== $point) {
                $current['points'][] = $point;
            }
        };

        foreach ($chain as [$fromNode, $edge]) {
            $toNode = $edge['to'];

            if ($fromNode === self::SOURCE) {
                $open($layoutOf($toNode) ?? (string) ($from['layout_id'] ?? ''));
                if (($from['type'] ?? 'location') === 'location') {
                    $push($from['point'] ?? null);
                }
                $push($pointOf($toNode));
                $current['distance'] += $edge['length'];
                $distance += $edge['length'];
                continue;
            }

            if ($toNode === self::TARGET) {
                if (($to['type'] ?? 'location') === 'location') {
                    $push($to['point'] ?? null);
                }
                $current['distance'] += $edge['length'];
                $distance += $edge['length'];
                continue;
            }

            if ($edge['kind'] === 'vertical' && $layoutOf($toNode) !== $current['layout_id']) {
                $current['transition'] = [
                    'kind'          => 'vertical',
                    'vertical_type' => $edge['vertical_type'],
                    'to_layout_id'  => $layoutOf($toNode),
                ];
                $steps[] = $current;
                $open($layoutOf($toNode));
                $push($pointOf($toNode));
                continue;
            }

            $push($pointOf($toNode));
            $current['distance'] += $edge['length'];
            $distance += $edge['length'];
        }

        if ($current !== null) {
            $steps[] = $current;
        }

        foreach ($steps as &$step) {
            $step['distance'] = round($step['distance'], 4);
        }

        return [
            'distance' => round($distance, 4),
            'cost'     => round($cost, 4),
            'steps'    => $steps,
        ];
    }
}
