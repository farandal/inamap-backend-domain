<?php

namespace Domain\App\Services\Inamap\Render;

use Domain\App\Models\Inamap\Inamap;
use Domain\App\Models\Inamap\Layout;
use Domain\App\Models\Inamap\Structure;
use Domain\App\Models\Inamap\Zone;
use Domain\App\Services\Inamap\Geometry\PolygonService;
use Domain\App\Services\Inamap\Qr\QrService;
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
 *
 * `places` (several `type:uuid` refs) marks each one with a numbered pin and
 * draws no path: for "where are the toilets", not "how do I get there".
 *
 * The route is ONE dashed line, the dash pattern running on through corners,
 * with no marker at its vertices.
 *
 * A band under the map carries a QR code that opens the same place (and the
 * same route) in the interactive viewer on a phone - a kiosk's picture is
 * useless to someone walking away from it. The band sits below the map, not
 * over it: a floor plan can be a thin strip that a corner overlay would
 * mostly cover. Left out when the inamap needs a session token to be viewed
 * (a bare link would not open on the phone) or when `qr` is false.
 */
class MapRenderService
{
    public const MIN_WIDTH = 240;
    public const MAX_WIDTH = 1600;
    public const MAX_PLACES = 12;

    /** Caption font, shipped with the QR library already required. */
    private const FONT = 'vendor/endroid/qr-code/assets/open_sans.ttf';

    private const CAPTIONS = [
        'es' => ['Llévate el mapa en tu celular', 'Escanea el código para abrir la ruta', 'Escanea el código para abrir el mapa'],
        'en' => ['Take the map with you', 'Scan the code to open the route', 'Scan the code to open the map'],
    ];

    public function __construct(
        private EndpointResolver $endpoints,
        private RouteService $routes,
        private PolygonService $polygons,
    ) {
    }

    /**
     * @param  array{focus?: ?string, from?: ?string, to?: ?string, places?: string[], layout?: ?string, accessible?: bool, width?: int, lang?: ?string, qr?: bool}  $options
     * @return string PNG bytes
     *
     * @throws RouteException when a reference does not belong to this inamap
     */
    public function render(Inamap $inamap, array $options): string
    {
        $placeRefs = array_slice(array_values(array_unique(array_filter((array) ($options['places'] ?? [])))), 0, self::MAX_PLACES);
        $places    = array_map(fn (string $ref) => $this->endpoints->resolve($inamap, $ref), $placeRefs);

        // Several places and no route: a set of locations, not a way to one.
        $focusRef = $places === [] ? ($options['focus'] ?? ($options['to'] ?? null)) : null;
        $focus    = $focusRef ? $this->endpoints->resolve($inamap, $focusRef) : null;
        $from     = $places === [] && !empty($options['from']) ? $this->endpoints->resolve($inamap, $options['from']) : null;

        $layout = $this->layoutFor($inamap, $options['layout'] ?? null, $focus ?? ($places[0] ?? null));
        $width  = max(self::MIN_WIDTH, min(self::MAX_WIDTH, (int) ($options['width'] ?? 900)));
        $scale  = $width / max(1e-6, (float) $layout->width);
        $mapHeight = max(1, (int) round((float) $layout->height * $scale));

        $accessible = (bool) ($options['accessible'] ?? false);
        $hasRoute   = $from && $focus;
        $qrUrl      = ($options['qr'] ?? true) && !$inamap->requiresToken()
            ? $this->phoneUrl(
                rtrim((string) config('inamap.route_sessions.app_url'), '/') . "/inamap/{$inamap->id}",
                $focusRef,
                $hasRoute ? $options['from'] : null,
                $accessible,
                (string) ($options['lang'] ?? 'es'),
                $placeRefs,
            )
            : null;
        $band   = $qrUrl ? $this->bandMetrics($width) : null;
        $height = $mapHeight + ($band['height'] ?? 0);

        $img = imagecreatetruecolor($width, $height);
        imagesavealpha($img, true);
        imageantialias($img, true);
        $this->drawBackground($img, $layout, $width, $mapHeight);

        $toPx = fn (array $p) => [(int) round($p[0] * $scale), (int) round($p[1] * $scale)];

        // Route first, so the markers sit on top of it.
        if ($hasRoute) {
            $route = $this->routes->route($inamap, $options['from'], $focusRef, $accessible);
            $this->dashedRoute($img, $this->joinSteps($route['steps'] ?? [], $layout->id, $toPx), max(3, (int) round($width / 150)));
        }

        foreach ($places as $i => $place) {
            $this->drawNumbered($img, $place, $layout, $toPx, $i + 1, max(3, (int) round($width / 150)));
        }
        if ($focus) {
            $this->drawEndpoint($img, $focus, $layout, $toPx, [225, 37, 27]);
        }
        if ($from && ($from['layout_id'] ?? null) === $layout->id) {
            $this->drawEndpoint($img, $from, $layout, $toPx, [33, 150, 83]);
        }

        if ($band) {
            $this->drawQrBand($img, $qrUrl, $band, $width, $mapHeight, (string) ($options['lang'] ?? 'es'), (bool) $hasRoute);
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

    /** One of several places: its outline when it is an area, and a numbered disc at its centre. */
    private function drawNumbered(\GdImage $img, array $endpoint, Layout $layout, callable $toPx, int $number, int $unit): void
    {
        if (($endpoint['layout_id'] ?? null) !== $layout->id) {
            return;
        }

        if (!empty($endpoint['polygon'])) {
            $points = array_map($toPx, $endpoint['polygon']);
            if (count($points) >= 3) {
                imagefilledpolygon($img, array_merge(...$points), imagecolorallocatealpha($img, 225, 37, 27, 85));
                $this->polyline($img, [...$points, $points[0]], 3, [225, 37, 27]);
            }
            [$x, $y] = $toPx($this->polygons->interiorPoint($endpoint['polygon']) ?? $this->polygons->centroid($endpoint['polygon']));
        } else {
            [$x, $y] = $toPx($endpoint['point'] ?? [0, 0]);
        }

        $outer = $unit * 6;
        imageantialias($img, false);
        imagefilledellipse($img, $x, $y, $outer + 6, $outer + 6, imagecolorallocate($img, 255, 255, 255));
        imagefilledellipse($img, $x, $y, $outer, $outer, imagecolorallocate($img, 225, 37, 27));
        imageantialias($img, true);

        $font = base_path(self::FONT);
        if (is_file($font) && function_exists('imagettftext')) {
            $size = max(9, (int) round($outer * 0.52));
            $box  = imagettfbbox($size, 0, $font, (string) $number);
            $w    = $box[2] - $box[0];
            $h    = $box[1] - $box[7];
            imagettftext($img, $size, 0, (int) round($x - $w / 2 - $box[0]), (int) round($y + $h / 2 - $box[1]), imagecolorallocate($img, 255, 255, 255), $font, (string) $number);
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

    /**
     * The route's steps on this floor as continuous paths. A route arrives as
     * many short steps (one per instruction); drawn one by one the dash pattern
     * would restart at each and read as beads. Consecutive steps that meet are
     * joined into one path; a jump (the route left this floor and came back)
     * starts another.
     *
     * @return array<int, array<int, array{0: int, 1: int}>>
     */
    private function joinSteps(array $steps, string $layoutId, callable $toPx): array
    {
        $paths   = [];
        $current = [];

        foreach ($steps as $step) {
            if (($step['layout_id'] ?? null) !== $layoutId || count($step['points'] ?? []) < 2) {
                continue;
            }
            $points = array_map($toPx, $step['points']);

            if ($current !== []) {
                $last = $current[array_key_last($current)];
                if (hypot($points[0][0] - $last[0], $points[0][1] - $last[1]) <= 12) {
                    array_shift($points); // the shared point
                    array_push($current, ...$points);
                    continue;
                }
                $paths[] = $current;
            }
            $current = $points;
        }

        if (count($current) > 1) {
            $paths[] = $current;
        }

        return $paths;
    }

    /**
     * The route as one dashed line. The dash pattern is carried along the whole
     * path, so a corner does not restart it and no vertex is marked: each dash
     * is stroked as a short polyline that simply bends where the path does.
     * White first (the edge that keeps it readable on any plan), then red.
     *
     * @param  array<int, array<int, array{0: int, 1: int}>>  $paths  pixel points, one list per route step
     */
    private function dashedRoute(\GdImage $img, array $paths, int $unit): void
    {
        $dashLength = $unit * 3.4;
        $gapLength  = $unit * 2.2;
        $dashes     = [];

        foreach ($paths as $points) {
            $drawing   = true;
            $remaining = $dashLength;
            $current   = [[$points[0][0], $points[0][1]]];

            for ($i = 1, $n = count($points); $i < $n; $i++) {
                [$x0, $y0] = $points[$i - 1];
                [$x1, $y1] = $points[$i];
                $length = hypot($x1 - $x0, $y1 - $y0);
                if ($length < 0.5) {
                    continue;
                }
                $dx  = ($x1 - $x0) / $length;
                $dy  = ($y1 - $y0) / $length;
                $pos = 0.0;

                while ($pos < $length - 1e-6) {
                    $step       = min($remaining, $length - $pos);
                    $pos       += $step;
                    $remaining -= $step;
                    $px = $x0 + $dx * $pos;
                    $py = $y0 + $dy * $pos;

                    if ($drawing) {
                        $current[] = [(int) round($px), (int) round($py)];
                    }
                    if ($remaining <= 1e-6) {
                        if ($drawing && count($current) > 1) {
                            $dashes[] = $current;
                        }
                        $drawing   = !$drawing;
                        $remaining = $drawing ? $dashLength : $gapLength;
                        $current   = $drawing ? [[(int) round($px), (int) round($py)]] : [];
                    }
                }
            }

            if ($drawing && count($current) > 1) {
                $dashes[] = $current;
            }
        }

        foreach ([[$unit * 2 - 1, [255, 255, 255]], [$unit, [225, 37, 27]]] as [$thickness, $rgb]) {
            foreach ($dashes as $dash) {
                $this->polyline($img, $dash, $thickness, $rgb);
            }
        }
    }

    /** Where a phone opens this same place and route in the interactive viewer. */
    private function phoneUrl(string $base, ?string $to, ?string $from, bool $accessible, string $lang, array $places = []): string
    {
        $query = array_filter([
            // One letter for the type (l, s, z) instead of the word: a long set
            // of places must stay a short enough link for a QR code.
            'places'     => $places === [] ? null : implode(',', array_map([$this, 'compactRef'], $places)),
            'to'         => $to,
            'from'       => $to ? $from : null,
            'accessible' => $to && $from && $accessible ? 1 : null,
            'lang'       => $lang === 'en' ? 'en' : null,
        ]);

        return $query === [] ? $base : $base . '?' . http_build_query($query);
    }

    /** `location:uuid` -> `l:uuid` (the viewer expands it back). */
    private function compactRef(string $ref): string
    {
        [$type, $id] = array_pad(explode(':', $ref, 2), 2, null);

        return in_array($type, ['location', 'structure', 'zone'], true) && $id ? $type[0] . ':' . $id : $ref;
    }

    /** @return array{qr: int, pad: int, height: int} */
    private function bandMetrics(int $width): array
    {
        $qr  = max(140, min(300, (int) round($width * 0.25)));
        $pad = max(8, (int) round($width * 0.012));

        return ['qr' => $qr, 'pad' => $pad, 'height' => $qr + 2 * $pad];
    }

    private function drawQrBand(\GdImage $img, string $url, array $band, int $width, int $top, string $lang, bool $hasRoute): void
    {
        ['qr' => $side, 'pad' => $pad, 'height' => $bandHeight] = $band;

        imagefilledrectangle($img, 0, $top, $width, $top + $bandHeight, imagecolorallocate($img, 255, 255, 255));
        imageline($img, 0, $top, $width, $top, imagecolorallocate($img, 214, 214, 210));

        $png = imagecreatefromstring(app(QrService::class)->render($url, 'png', $side, compact: true)['content']);
        if ($png) {
            imagecopyresampled($img, $png, $width - $pad - $side, $top + $pad, 0, 0, $side, $side, imagesx($png), imagesy($png));
            imagedestroy($png);
        }

        // The caption only when it fits beside the code and the font is there.
        $font      = base_path(self::FONT);
        $available = $width - $side - 3 * $pad;
        if ($available < 150 || !is_file($font) || !function_exists('imagettftext')) {
            return;
        }

        [$title, $routeLine, $mapLine] = self::CAPTIONS[$lang === 'en' ? 'en' : 'es'];
        $ink      = imagecolorallocate($img, 38, 38, 38);
        $muted    = imagecolorallocate($img, 96, 96, 96);
        $titleSize = max(11, (int) round($width * 0.03));
        $lineSize  = max(9, (int) round($width * 0.022));
        // Shrink to fit rather than clip.
        while ($titleSize > 9 && ($box = imagettfbbox($titleSize, 0, $font, $title)) && ($box[2] - $box[0]) > $available) {
            $titleSize--;
            $lineSize = max(8, $lineSize - 1);
        }

        $middle = $top + intdiv($bandHeight, 2);
        imagettftext($img, $titleSize, 0, $pad * 2, $middle - (int) round($titleSize * 0.2), $ink, $font, $title);
        imagettftext($img, $lineSize, 0, $pad * 2, $middle + (int) round($lineSize * 1.9), $muted, $font, $hasRoute ? $routeLine : $mapLine);
    }

    private function polyline(\GdImage $img, array $points, int $thickness, array $rgb): void
    {
        $color = imagecolorallocate($img, ...$rgb);
        // GD ignores a line's thickness while antialiasing is on, which leaves a
        // 1px line with a dot at each point - a string of beads. Thick strokes
        // are drawn with it off.
        imageantialias($img, false);
        imagesetthickness($img, $thickness);
        for ($i = 1, $n = count($points); $i < $n; $i++) {
            imageline($img, $points[$i - 1][0], $points[$i - 1][1], $points[$i][0], $points[$i][1], $color);
        }
        // Round joints, so a thick line has no gaps at its corners.
        foreach ($points as [$x, $y]) {
            imagefilledellipse($img, $x, $y, $thickness, $thickness, $color);
        }
        imagesetthickness($img, 1);
        imageantialias($img, true);
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
