<?php

namespace Domain\App\Services\Inamap\Routing;

/**
 * Grafo dirigido y ponderado derivado de caminos y enlaces (README ER-03).
 *
 * - weight: costo de optimización (incluye penalizaciones de enlaces verticales).
 * - length: distancia física recorrida (0 en enlaces verticales).
 * Ambos en la unidad del inamap.
 */
class Graph
{
    /** @var array<string, array{layout_id: string, x: float, y: float}> */
    public array $nodes = [];

    /** @var array<string, list<array{to: string, weight: float, length: float, kind: string, vertical_type: ?string, accessible: bool}>> */
    public array $adjacency = [];

    /**
     * Segmentos recorribles, para proyectar orígenes/destinos sobre caminos.
     *
     * @var list<array{layout_id: string, path_id: string, a: string, b: string, forward: bool, backward: bool}>
     */
    public array $segments = [];

    /** @var array<string, list<string>> path_id => nodos en orden */
    public array $pathNodes = [];

    public function addNode(string $id, string $layoutId, float $x, float $y): void
    {
        $this->nodes[$id] = ['layout_id' => $layoutId, 'x' => $x, 'y' => $y];
        $this->adjacency[$id] ??= [];
    }

    public function addEdge(string $from, string $to, float $weight, float $length, string $kind = 'segment', ?string $verticalType = null, bool $accessible = true): void
    {
        if ($from === $to) {
            return;
        }

        $this->adjacency[$from][] = [
            'to'            => $to,
            'weight'        => $weight,
            'length'        => $length,
            'kind'          => $kind,
            'vertical_type' => $verticalType,
            'accessible'    => $accessible,
        ];
    }

    public function point(string $nodeId): array
    {
        return [$this->nodes[$nodeId]['x'], $this->nodes[$nodeId]['y']];
    }

    public function layoutOf(string $nodeId): ?string
    {
        return $this->nodes[$nodeId]['layout_id'] ?? null;
    }

    /** Para cachear el grafo (Redis) sin depender de serialize() de objetos. */
    public function toArray(): array
    {
        return [
            'nodes'     => $this->nodes,
            'adjacency' => $this->adjacency,
            'segments'  => $this->segments,
            'pathNodes' => $this->pathNodes,
        ];
    }

    public static function fromArray(array $data): self
    {
        $graph = new self();
        $graph->nodes = $data['nodes'] ?? [];
        $graph->adjacency = $data['adjacency'] ?? [];
        $graph->segments = $data['segments'] ?? [];
        $graph->pathNodes = $data['pathNodes'] ?? [];

        return $graph;
    }
}
