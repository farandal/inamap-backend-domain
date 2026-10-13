<?php

namespace Domain\App\Services\Inamap\Render;

use Domain\App\Models\Inamap\Inamap;
use Domain\App\Models\Inamap\Layout;
use Domain\App\Models\Inamap\Structure;
use Domain\App\Models\Inamap\Zone;
use Domain\App\Services\Inamap\Geometry\PolygonService;
use Domain\App\Services\Inamap\Routing\EndpointResolver;
use Domain\App\Services\Inamap\Routing\RouteException;
use Domain\App\Services\Inamap\Routing\RouteService;
use Illuminate\Support\Facades\Storage;

/**
 * A still image (PNG) of one floor of an inamap with a place and, optionally,
 * the route to it highlighted.
 *
 * For screens that cannot run the interactive viewer (a kiosk card, an email,
 * a chat message): the caller passes references only (`focus`, `from`, `to`
 * as `type:uuid`) and every coordinate comes from the inamap itself.
 *
 * Coordinates are in the inamap unit with the origin top-left, the layout
 * spanning (0,0)-(width,height); the floor plan image, when there is one, is
 * stretched to that box exactly as the viewer draws it.
 */
class MapRenderService
{
    public const MIN_WIDTH = 240;
    public const MAX_WIDTH = 1600;

    public function __construct(
        private EndpointResolver $endpoints,
        private RouteService $routes,
        private PolygonService $polygons,
    ) {
    }

    /**
     * @param  array{focus?: ?string, from?: ?string, to?: ?string, layout?: ?string, accessible?: bool, width?: int}  $options
     * @return string PNG bytes
     *
     * @throws RouteException when a reference does not belong to this inamap
     */
    public function render(Inamap $inamap, array $options): string
    {
        $focusRef = $options['focus'] ?? ($options['to'] ?? null);
        $focus    = $focusRef ? $this->endpoints->resolve($inamap, $focusRef) : null;
        $from     = !empty($options['from']) ? $this->endpoints->resolve($inamap, $options['from']) : null;

        $layout = $this->layoutFor($inamap, $options['layout'] ?? null, $focus);
        $width  = max(self::MIN_WIDTH, min(self::MAX_WIDTH, (int) ($options['width'] ?? 900)));
        $scale  = $width / max(1e-6, (float) $layout->width);
        $height = max(1, (int) round((float) $layout->height * $scale));

        $img = imagecreatetruecolor($width, $height);
        imagesavealpha($img, true);
        imageantialias($img, true);
        $this->drawBackground($img, $layout, $width, $height);

        $toPx = fn (array $p) => [(int) round($p[0] * $scale), (int) round($p[1] * $scale)];

        // Route first, so the markers sit on top of it.
        if ($from && $focus) {
            $route = $this->routes->route($inamap, $options['from'], $focusRef, (bool) ($options['accessible'] ?? false));
            foreach ($route['steps'] ?? [] as $step) {
                if (($step['layout_id'] ?? null) === $layout->id && count($step['points'] ?? []) > 1) {
                    $this->polyline($img, array_map($toPx, $step['points']), 9, [255, 255, 255]);
                    $this->polyline($img, array_map($toPx, $step['points']), 5, [225, 37, 27]);
                }
            }
        }

        if ($focus) {
            $this->drawEndpoint($img, $focus, $layout, $toPx, [225, 37, 27]);
        }
        if ($from && ($from['layout_id'] ?? null) === $layout->id) {
            $this->drawEndpoint($img, $from, $layout, $toPx, [33, 150, 83]);
        }

        ob_start();
        imagepng($img, null, 6);
        $png = (string) ob_get_clean();
        imagedestroy($img);

        return $png;
    }

    private function layoutFor(Inamap $inamap, ?string $layoutId, ?array $focus): Layout
    {
        $layouts = $inamap->layouts()->get();
        $wanted  = $layoutId ?: ($focus['layout_id'] ?? null) ?: ($inamap->settings['initial_layout_id'] ?? null);

        $layout = $layouts->firstWhere('id', $wanted) ?? $layouts->first();
        if (!$layout) {
            throw new RouteException(RouteException::ENDPOINT_NOT_FOUND, 'El inamap no tiene layouts');
        }

        return $layout;
    }

    private function drawBackground(\GdImage $img, Layout $layout, int $width, int $height): void
    {
        imagefill($img, 0, 0, imagecolorallocate($img, 245, 245, 242));

        $plan = null;
        if ($layout->image_path) {
            try {
                $bytes = Storage::disk(config('app.media_disk', 'local'))->get($layout->image_path);
                $plan  = $bytes ? @imagecreatefromstring($bytes) : null;
            } catch (\Throwable) {
                $plan = null;
            }
        }

        if ($plan) {
            imagecopyresampled($img, $plan, 0, 0, 0, 0, $width, $height, imagesx($plan), imagesy($plan));
            imagedestroy($plan);

            return;
        }

        // No plan image: draw the zones and structures themselves.
        $scale = $width / max(1e-6, (float) $layout->width);
        foreach ([Zone::class => 90, Structure::class => 40] as $class => $alpha) {
            foreach ($class::where('layout_id', $layout->id)->get(['polygon', 'color']) as $shape) {
                $points = array_map(fn ($p) => [(int) round($p[0] * $scale), (int) round($p[1] * $scale)], (array) $shape->polygon);
                if (count($points) >= 3) {
                    [$r, $g, $b] = $this->rgb((string) ($shape->color ?: '#9e9e9e'));
                    imagefilledpolygon($img, array_merge(...$points), imagecolorallocatealpha($img, $r, $g, $b, $alpha));
                }
            }
        }
    }

    /** A pin for a point, an outline for an area. */
    private function drawEndpoint(\GdImage $img, array $endpoint, Layout $layout, callable $toPx, array $rgb): void
    {
        if (($endpoint['layout_id'] ?? null) !== $layout->id) {
            return;
        }

        [$r, $g, $b] = $rgb;

        if (!empty($endpoint['polygon'])) {
            $points = array_map($toPx, $endpoint['polygon']);
            if (count($points) >= 3) {
                imagefilledpolygon($img, array_merge(...$points), imagecolorallocatealpha($img, $r, $g, $b, 85));
                $this->polyline($img, [...$points, $points[0]], 4, $rgb);
            }
            $center = $toPx($this->polygons->interiorPoint($endpoint['polygon']) ?? $this->polygons->centroid($endpoint['polygon']));
        } else {
            $center = $toPx($endpoint['point'] ?? [0, 0]);
        }

        [$x, $y] = $center;
        imagefilledellipse($img, $x, $y, 30, 30, imagecolorallocate($img, 255, 255, 255));
        imagefilledellipse($img, $x, $y, 22, 22, imagecolorallocate($img, $r, $g, $b));
        imagefilledellipse($img, $x, $y, 8, 8, imagecolorallocate($img, 255, 255, 255));
    }

    private function polyline(\GdImage $img, array $points, int $thickness, array $rgb): void
    {
        $color = imagecolorallocate($img, ...$rgb);
        imagesetthickness($img, $thickness);
        for ($i = 1, $n = count($points); $i < $n; $i++) {
            imageline($img, $points[$i - 1][0], $points[$i - 1][1], $points[$i][0], $points[$i][1], $color);
        }
        // Round joints, so a thick line has no gaps at its corners.
        foreach ($points as [$x, $y]) {
            imagefilledellipse($img, $x, $y, $thickness, $thickness, $color);
        }
        imagesetthickness($img, 1);
    }

    /** @return int[] */
    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return strlen($hex) === 6 ? array_map('hexdec', str_split($hex, 2)) : [158, 158, 158];
    }
}
