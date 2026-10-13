<?php

namespace Domain\Tests\Unit\Inamap;

use Domain\App\Services\Inamap\Render\MapRenderService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The route drawn on the map picture is one dashed line - the pattern runs on
 * through corners and nothing marks a vertex - and the QR code under the map
 * opens the same place and route on a phone.
 */
class MapRenderServiceTest extends TestCase
{
    private const WHITE = 0xFFFFFF;

    private MapRenderService $service;

    protected function setUp(): void
    {
        $this->service = (new \ReflectionClass(MapRenderService::class))->newInstanceWithoutConstructor();
    }

    private function call(string $method, mixed ...$args): mixed
    {
        $m = new \ReflectionMethod(MapRenderService::class, $method);

        return $m->invoke($this->service, ...$args);
    }

    private function canvas(): \GdImage
    {
        $img = imagecreatetruecolor(200, 140);
        // As the service builds it: with antialiasing on, which makes GD ignore line thickness.
        imageantialias($img, true);
        imagefill($img, 0, 0, self::WHITE);

        return $img;
    }

    /** Is the pixel the route's red (not the white halo, not background)? */
    private function isRed(\GdImage $img, int $x, int $y): bool
    {
        $c = imagecolorat($img, $x, $y);

        return (($c >> 16) & 0xFF) > 180 && (($c >> 8) & 0xFF) < 90 && ($c & 0xFF) < 90;
    }

    #[Test]
    public function the_route_is_dashed_with_gaps_and_no_marker_at_a_corner_that_falls_in_a_gap(): void
    {
        $img = $this->canvas();
        // unit 5: a dash is 17 px and a gap 11, repeating along the path.
        // Right 22 px (the corner lands inside the first gap, 17-28), then down.
        $this->call('dashedRoute', $img, [[[10, 50], [32, 50], [32, 120]]], 5);

        $this->assertTrue($this->isRed($img, 18, 50), 'the first dash is drawn');
        $this->assertFalse($this->isRed($img, 32, 50), 'no marker on the vertex');
        $this->assertFalse($this->isRed($img, 31, 50), 'the gap is empty');
        // The pattern carried on round the corner: the next dash starts 6 px past it.
        $this->assertTrue($this->isRed($img, 32, 62), 'the dash after the corner is drawn');
        $this->assertFalse($this->isRed($img, 32, 78), 'and its following gap is empty: the pattern did not restart');
        $this->assertTrue($this->isRed($img, 32, 90), 'then the third dash');
    }

    #[Test]
    public function a_dash_is_a_solid_stroke_even_when_the_route_has_many_close_points(): void
    {
        $img = $this->canvas();
        // A real route: a point every 2 px. The first dash (17 px) must be one
        // unbroken stroke, not a thin line with dots on it.
        $points = [];
        for ($x = 10; $x <= 110; $x += 2) {
            $points[] = [$x, 70];
        }
        $this->call('dashedRoute', $img, [$points], 5);

        for ($x = 10; $x <= 26; $x++) {
            $this->assertTrue($this->isRed($img, $x, 70), "dash broken at x={$x}");
            $this->assertTrue($this->isRed($img, $x, 68), "dash is thin at x={$x}");
        }
        $this->assertFalse($this->isRed($img, 33, 70), 'the gap is empty');
        $this->assertTrue($this->isRed($img, 45, 70), 'the pattern keeps its period over the dense points');
    }

    #[Test]
    public function a_corner_inside_a_dash_bends_it_instead_of_breaking_the_line(): void
    {
        $img = $this->canvas();
        // The corner at 8 px, well inside the first 17 px dash.
        $this->call('dashedRoute', $img, [[[10, 50], [18, 50], [18, 90]]], 5);

        $this->assertTrue($this->isRed($img, 14, 50));
        $this->assertTrue($this->isRed($img, 18, 50), 'the bend is part of the dash');
        $this->assertTrue($this->isRed($img, 18, 56));
    }

    #[Test]
    public function two_floors_do_not_connect_with_a_stray_dash(): void
    {
        $img = $this->canvas();
        $this->call('dashedRoute', $img, [[[10, 20], [60, 20]], [[10, 100], [60, 100]]], 5);

        // Nothing is drawn between the two separate paths.
        for ($y = 35; $y <= 85; $y += 5) {
            $this->assertFalse($this->isRed($img, 10, $y), "stray red at y={$y}");
        }
        $this->assertTrue($this->isRed($img, 18, 100), 'the second path starts with a fresh dash');
    }

    #[Test]
    public function steps_that_meet_are_one_path_so_the_dash_pattern_does_not_restart_at_each(): void
    {
        $toPx = fn (array $p) => [(int) $p[0], (int) $p[1]];
        $steps = [
            ['layout_id' => 'L', 'points' => [[0, 0], [10, 0]]],
            ['layout_id' => 'L', 'points' => [[10, 0], [10, 10]]],
            ['layout_id' => 'OTHER', 'points' => [[500, 500], [510, 500]]],
            ['layout_id' => 'L', 'points' => [[10, 10], [20, 10]]],
            ['layout_id' => 'L', 'points' => [[100, 100], [110, 100]]], // came back somewhere else
        ];

        $paths = $this->call('joinSteps', $steps, 'L', $toPx);

        $this->assertSame([
            [[0, 0], [10, 0], [10, 10], [20, 10]],
            [[100, 100], [110, 100]],
        ], $paths);
    }

    #[Test]
    public function the_phone_link_opens_the_same_place_and_route(): void
    {
        $base = 'https://app.test/inamap/abc';

        $this->assertSame(
            $base . '?to=structure%3AT&from=location%3AK&accessible=1',
            $this->call('phoneUrl', $base, 'structure:T', 'location:K', true, 'es'),
        );
        $this->assertSame($base . '?to=structure%3AT&lang=en', $this->call('phoneUrl', $base, 'structure:T', null, true, 'en'), 'no route, no accessible flag');
        $this->assertSame($base, $this->call('phoneUrl', $base, null, null, false, 'es'), 'a whole-venue picture links to the map');
        $this->assertSame($base . '?to=structure%3AT&from=location%3AK', $this->call('phoneUrl', $base, 'structure:T', 'location:K', false, 'es'), 'not accessible: no flag');
    }
}
