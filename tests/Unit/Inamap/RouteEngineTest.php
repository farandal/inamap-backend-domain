<?php

namespace Domain\Tests\Unit\Inamap;

use Domain\App\Services\Inamap\Routing\Graph;
use Domain\App\Services\Inamap\Routing\PathGraphBuilder;
use Domain\App\Services\Inamap\Routing\RouteEngine;
use Domain\App\Services\Inamap\Routing\RouteException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RouteEngineTest extends TestCase
{
    private const VERTICAL_TYPES = [
        'elevator'  => ['accessible' => true,  'default_cost_m' => 15.0],
        'ramp'      => ['accessible' => true,  'default_cost_m' => 5.0],
        'stairs'    => ['accessible' => false, 'default_cost_m' => 8.0],
        'escalator' => ['accessible' => false, 'default_cost_m' => 6.0],
    ];

    private function build(array $paths, array $links = []): Graph
    {
        return (new PathGraphBuilder())->build($paths, $links, [
            'tolerance'      => 0.3,
            'to_meters'      => 1.0,
            'vertical_types' => self::VERTICAL_TYPES,
        ]);
    }

    private function location(string $layout, float $x, float $y): array
    {
        return ['type' => 'location', 'layout_id' => $layout, 'point' => [$x, $y]];
    }

    #[Test]
    public function routes_along_a_single_path(): void
    {
        $graph = $this->build([['id' => 'p1', 'layout_id' => 'L1', 'points' => [[0, 0], [10, 0], [10, 10]]]]);

        $route = (new RouteEngine())->route($graph, $this->location('L1', 0, 1), $this->location('L1', 11, 10));

        $this->assertCount(1, $route['steps']);
        // 1 m hasta el camino + 10 + 10 por el camino + 1 m hasta el destino.
        $this->assertEqualsWithDelta(22.0, $route['distance'], 1e-6);
        $this->assertSame([0.0, 1.0], $route['steps'][0]['points'][0]);
    }

    #[Test]
    public function one_way_path_cannot_be_walked_backwards(): void
    {
        $graph = $this->build([['id' => 'p1', 'layout_id' => 'L1', 'points' => [[0, 0], [10, 0]], 'direction' => 'forward']]);
        $engine = new RouteEngine();

        $this->assertEqualsWithDelta(10.0, $engine->route($graph, $this->location('L1', 0, 0), $this->location('L1', 10, 0))['distance'], 1e-6);

        $this->expectException(RouteException::class);
        $engine->route($graph, $this->location('L1', 10, 0), $this->location('L1', 0, 0));
    }

    #[Test]
    public function one_way_is_respected_within_a_single_segment(): void
    {
        $graph = $this->build([['id' => 'p1', 'layout_id' => 'L1', 'points' => [[0, 0], [10, 0]], 'direction' => 'forward']]);

        try {
            (new RouteEngine())->route($graph, $this->location('L1', 7, 0), $this->location('L1', 3, 0));
            $this->fail('Expected UNREACHABLE');
        } catch (RouteException $e) {
            $this->assertSame(RouteException::UNREACHABLE, $e->errorCode);
        }
    }

    #[Test]
    public function prefers_a_two_way_detour_over_a_wrong_way_shortcut(): void
    {
        $graph = $this->build([
            ['id' => 'short', 'layout_id' => 'L1', 'points' => [[10, 0], [0, 0]], 'direction' => 'forward'],
            ['id' => 'detour', 'layout_id' => 'L1', 'points' => [[0, 0], [0, 5], [10, 5], [10, 0]]],
        ]);

        $route = (new RouteEngine())->route($graph, $this->location('L1', 0, 0), $this->location('L1', 10, 0));

        $this->assertEqualsWithDelta(20.0, $route['distance'], 1e-6);
    }

    #[Test]
    public function coincident_vertices_connect_paths(): void
    {
        $graph = $this->build([
            ['id' => 'a', 'layout_id' => 'L1', 'points' => [[0, 0], [10, 0]]],
            ['id' => 'b', 'layout_id' => 'L1', 'points' => [[10.1, 0.1], [10, 10]]],
        ]);

        $route = (new RouteEngine())->route($graph, $this->location('L1', 0, 0), $this->location('L1', 10, 10));

        $this->assertCount(1, $route['steps']);
    }

    #[Test]
    public function a_path_ending_on_another_paths_segment_is_joined(): void
    {
        $graph = $this->build([
            ['id' => 'main', 'layout_id' => 'L1', 'points' => [[0, 0], [20, 0]]],
            ['id' => 'branch', 'layout_id' => 'L1', 'points' => [[10, 10], [10, 0]]],
        ]);

        $route = (new RouteEngine())->route($graph, $this->location('L1', 0, 0), $this->location('L1', 10, 10));

        $this->assertEqualsWithDelta(20.0, $route['distance'], 1e-6);
    }

    #[Test]
    public function an_explicit_link_joins_disconnected_paths(): void
    {
        $paths = [
            ['id' => 'a', 'layout_id' => 'L1', 'points' => [[0, 0], [10, 0]]],
            ['id' => 'b', 'layout_id' => 'L1', 'points' => [[12, 0], [20, 0]]],
        ];
        $engine = new RouteEngine();

        try {
            $engine->route($this->build($paths), $this->location('L1', 0, 0), $this->location('L1', 20, 0));
            $this->fail('Expected UNREACHABLE without the link');
        } catch (RouteException $e) {
            $this->assertSame(RouteException::UNREACHABLE, $e->errorCode);
        }

        $graph = $this->build($paths, [['from_path_id' => 'a', 'from_end' => 'end', 'to_path_id' => 'b', 'to_end' => 'start', 'kind' => 'link']]);

        $this->assertEqualsWithDelta(20.0, $engine->route($graph, $this->location('L1', 0, 0), $this->location('L1', 20, 0))['distance'], 1e-6);
    }

    #[Test]
    public function crosses_layouts_through_a_typed_vertical_link(): void
    {
        $graph = $this->build(
            [
                ['id' => 'f1', 'layout_id' => 'L1', 'points' => [[0, 0], [10, 0]]],
                ['id' => 'f2', 'layout_id' => 'L2', 'points' => [[10, 0], [10, 10]]],
            ],
            [['from_path_id' => 'f1', 'from_end' => 'end', 'to_path_id' => 'f2', 'to_end' => 'start', 'kind' => 'vertical', 'vertical_type' => 'elevator']],
        );

        $route = (new RouteEngine())->route($graph, $this->location('L1', 0, 0), $this->location('L2', 10, 10));

        $this->assertCount(2, $route['steps']);
        $this->assertSame('L1', $route['steps'][0]['layout_id']);
        $this->assertSame(['kind' => 'vertical', 'vertical_type' => 'elevator', 'to_layout_id' => 'L2'], $route['steps'][0]['transition']);
        $this->assertSame('L2', $route['steps'][1]['layout_id']);
        $this->assertEqualsWithDelta(20.0, $route['distance'], 1e-6);
        $this->assertEqualsWithDelta(35.0, $route['cost'], 1e-6);
    }

    #[Test]
    public function accessible_route_avoids_stairs_and_reports_why_it_fails(): void
    {
        $paths = [
            ['id' => 'f1', 'layout_id' => 'L1', 'points' => [[0, 0], [10, 0]]],
            ['id' => 'f2', 'layout_id' => 'L2', 'points' => [[10, 0], [10, 10]]],
            ['id' => 'f1b', 'layout_id' => 'L1', 'points' => [[0, 0], [0, 30]]],
            ['id' => 'f2b', 'layout_id' => 'L2', 'points' => [[0, 30], [10, 10]]],
        ];
        $stairs = ['from_path_id' => 'f1', 'from_end' => 'end', 'to_path_id' => 'f2', 'to_end' => 'start', 'kind' => 'vertical', 'vertical_type' => 'stairs'];
        $elevator = ['from_path_id' => 'f1b', 'from_end' => 'end', 'to_path_id' => 'f2b', 'to_end' => 'start', 'kind' => 'vertical', 'vertical_type' => 'elevator'];
        $engine = new RouteEngine();

        $graph = $this->build($paths, [$stairs, $elevator]);
        $normal = $engine->route($graph, $this->location('L1', 0, 0), $this->location('L2', 10, 10));
        $accessible = $engine->route($graph, $this->location('L1', 0, 0), $this->location('L2', 10, 10), true);

        $this->assertSame('stairs', $normal['steps'][0]['transition']['vertical_type']);
        $this->assertSame('elevator', $accessible['steps'][0]['transition']['vertical_type']);

        try {
            $engine->route($this->build($paths, [$stairs]), $this->location('L1', 0, 0), $this->location('L2', 10, 10), true);
            $this->fail('Expected UNREACHABLE_ACCESSIBLE');
        } catch (RouteException $e) {
            $this->assertSame(RouteException::UNREACHABLE_ACCESSIBLE, $e->errorCode);
        }
    }

    #[Test]
    public function zone_endpoints_use_nodes_inside_the_polygon(): void
    {
        $graph = $this->build([['id' => 'p', 'layout_id' => 'L1', 'points' => [[0, 0], [10, 0], [20, 0]]]]);
        $zone = ['type' => 'zone', 'layout_id' => 'L1', 'polygon' => [[8, -2], [12, -2], [12, 2], [8, 2]]];

        $route = (new RouteEngine())->route($graph, $this->location('L1', 0, 0), $zone);

        $this->assertEqualsWithDelta(8.0, $route['distance'], 1e-6);
    }

    #[Test]
    public function endpoint_on_a_layout_without_paths_is_rejected(): void
    {
        $graph = $this->build([['id' => 'p', 'layout_id' => 'L1', 'points' => [[0, 0], [10, 0]]]]);

        try {
            (new RouteEngine())->route($graph, $this->location('L1', 0, 0), $this->location('L9', 1, 1));
            $this->fail('Expected NO_PATH_NEAR_ENDPOINT');
        } catch (RouteException $e) {
            $this->assertSame(RouteException::NO_PATH_NEAR_ENDPOINT, $e->errorCode);
        }
    }

    #[Test]
    public function graph_survives_array_round_trip_for_caching(): void
    {
        $graph = $this->build([['id' => 'p1', 'layout_id' => 'L1', 'points' => [[0, 0], [10, 0]]]]);
        $restored = Graph::fromArray(json_decode(json_encode($graph->toArray()), true));

        $route = (new RouteEngine())->route($restored, $this->location('L1', 0, 0), $this->location('L1', 10, 0));

        $this->assertEqualsWithDelta(10.0, $route['distance'], 1e-6);
    }
}
