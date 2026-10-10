<?php

namespace Domain\Tests\Unit\Inamap;

use Domain\App\Services\Inamap\Geometry\PolygonService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PolygonServiceTest extends TestCase
{
    private PolygonService $geometry;

    protected function setUp(): void
    {
        $this->geometry = new PolygonService();
    }

    private function square(float $x, float $y, float $size): array
    {
        return [[$x, $y], [$x + $size, $y], [$x + $size, $y + $size], [$x, $y + $size]];
    }

    #[Test]
    public function partially_overlapping_squares_overlap(): void
    {
        $this->assertTrue($this->geometry->overlaps($this->square(0, 0, 10), $this->square(5, 5, 10)));
    }

    #[Test]
    public function squares_sharing_an_edge_do_not_overlap(): void
    {
        $this->assertFalse($this->geometry->overlaps($this->square(0, 0, 10), $this->square(10, 0, 10)));
    }

    #[Test]
    public function squares_sharing_a_vertex_do_not_overlap(): void
    {
        $this->assertFalse($this->geometry->overlaps($this->square(0, 0, 10), $this->square(10, 10, 10)));
    }

    #[Test]
    public function a_contained_polygon_overlaps(): void
    {
        $this->assertTrue($this->geometry->overlaps($this->square(0, 0, 10), $this->square(2, 2, 3)));
        $this->assertTrue($this->geometry->overlaps($this->square(2, 2, 3), $this->square(0, 0, 10)));
    }

    #[Test]
    public function identical_polygons_overlap(): void
    {
        $this->assertTrue($this->geometry->overlaps($this->square(0, 0, 10), $this->square(0, 0, 10)));
    }

    #[Test]
    public function an_inner_polygon_sharing_an_edge_overlaps(): void
    {
        $this->assertTrue($this->geometry->overlaps($this->square(0, 0, 10), [[0, 0], [10, 0], [10, 5], [0, 5]]));
    }

    #[Test]
    public function a_cross_shape_overlaps_without_containing_vertices(): void
    {
        $horizontal = [[0, 4], [10, 4], [10, 6], [0, 6]];
        $vertical = [[4, 0], [6, 0], [6, 10], [4, 10]];

        $this->assertTrue($this->geometry->overlaps($horizontal, $vertical));
    }

    #[Test]
    public function separate_polygons_do_not_overlap(): void
    {
        $this->assertFalse($this->geometry->overlaps($this->square(0, 0, 5), $this->square(20, 20, 5)));
    }

    #[Test]
    public function self_intersecting_polygon_is_not_simple(): void
    {
        $this->assertFalse($this->geometry->isSimple([[0, 0], [10, 10], [10, 0], [0, 10]]));
        $this->assertTrue($this->geometry->isSimple($this->square(0, 0, 10)));
        $this->assertFalse($this->geometry->isSimple([[0, 0], [5, 5], [10, 10]]));
    }

    #[Test]
    public function concave_polygon_has_an_interior_point_inside_it(): void
    {
        $l = [[0, 0], [10, 0], [10, 2], [2, 2], [2, 10], [0, 10]];
        $point = $this->geometry->interiorPoint($l);

        $this->assertNotNull($point);
        $this->assertTrue($this->geometry->containsStrictly($l, $point));
    }

    #[Test]
    public function closing_point_is_ignored(): void
    {
        $closed = [...$this->square(0, 0, 10), [0, 0]];

        $this->assertTrue($this->geometry->isSimple($closed));
        $this->assertEqualsWithDelta([5, 5], $this->geometry->centroid($closed), 1e-9);
    }
}
