<?php

namespace Domain\App\Services\Inamap\Geometry;

/**
 * Geometría plana sobre polígonos y polilíneas en el sistema de coordenadas
 * del inamap (sin PostGIS, H9). PHP puro: sin dependencias de Laravel para
 * poder probarse de forma aislada.
 *
 * Polígono: lista de puntos [[x, y], ...], cerrado implícitamente.
 */
class PolygonService
{
    public const EPSILON = 1e-9;

    /** Polígono válido: ≥ 3 vértices, área no nula y sin autointersecciones. */
    public function isSimple(array $polygon): bool
    {
        $polygon = $this->normalize($polygon);
        $n = count($polygon);

        if ($n < 3 || abs($this->signedArea($polygon)) < self::EPSILON) {
            return false;
        }

        for ($i = 0; $i < $n; $i++) {
            $a1 = $polygon[$i];
            $a2 = $polygon[($i + 1) % $n];

            for ($j = $i + 1; $j < $n; $j++) {
                // Aristas adyacentes comparten un vértice por definición.
                if ($j === $i || ($j + 1) % $n === $i || ($i + 1) % $n === $j) {
                    continue;
                }

                if ($this->segmentsIntersect($a1, $a2, $polygon[$j], $polygon[($j + 1) % $n])) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * ¿Se solapan los interiores de dos polígonos? Compartir borde o vértice
     * NO es solapamiento (RF-06).
     */
    public function overlaps(array $a, array $b): bool
    {
        $a = $this->normalize($a);
        $b = $this->normalize($b);

        if (!$this->boundingBoxesOverlap($a, $b)) {
            return false;
        }

        // 1. Cruce propio de aristas (no solo contacto).
        $na = count($a);
        $nb = count($b);
        for ($i = 0; $i < $na; $i++) {
            for ($j = 0; $j < $nb; $j++) {
                if ($this->segmentsCrossProperly($a[$i], $a[($i + 1) % $na], $b[$j], $b[($j + 1) % $nb])) {
                    return true;
                }
            }
        }

        // 2. Un vértice, punto medio de arista o punto interior de uno
        //    estrictamente dentro del otro (contención, polígonos idénticos,
        //    aristas colineales parcialmente superpuestas).
        foreach ([[$a, $b], [$b, $a]] as [$p, $q]) {
            foreach ($this->probePoints($p) as $point) {
                if ($this->containsStrictly($q, $point)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Punto dentro del polígono o sobre su borde. */
    public function contains(array $polygon, array $point): bool
    {
        $polygon = $this->normalize($polygon);

        return $this->onBoundary($polygon, $point) || $this->rayCast($polygon, $point);
    }

    /** Punto dentro del polígono y no sobre su borde. */
    public function containsStrictly(array $polygon, array $point): bool
    {
        $polygon = $this->normalize($polygon);

        return !$this->onBoundary($polygon, $point) && $this->rayCast($polygon, $point);
    }

    public function onBoundary(array $polygon, array $point, float $tolerance = 1e-7): bool
    {
        $polygon = $this->normalize($polygon);
        $n = count($polygon);

        for ($i = 0; $i < $n; $i++) {
            if ($this->distanceToSegment($point, $polygon[$i], $polygon[($i + 1) % $n]) <= $tolerance) {
                return true;
            }
        }

        return false;
    }

    public function distanceToBoundary(array $polygon, array $point): float
    {
        $polygon = $this->normalize($polygon);
        $n = count($polygon);
        $min = INF;

        for ($i = 0; $i < $n; $i++) {
            $min = min($min, $this->distanceToSegment($point, $polygon[$i], $polygon[($i + 1) % $n]));
        }

        return $min;
    }

    /** Centroide de área; si el polígono es degenerado, promedio de vértices. */
    public function centroid(array $polygon): array
    {
        $polygon = $this->normalize($polygon);
        $area = $this->signedArea($polygon);
        $n = count($polygon);

        if (abs($area) < self::EPSILON) {
            $sx = array_sum(array_column($polygon, 0));
            $sy = array_sum(array_column($polygon, 1));

            return [$sx / max($n, 1), $sy / max($n, 1)];
        }

        $cx = 0.0;
        $cy = 0.0;
        for ($i = 0; $i < $n; $i++) {
            [$x1, $y1] = $polygon[$i];
            [$x2, $y2] = $polygon[($i + 1) % $n];
            $cross = $x1 * $y2 - $x2 * $y1;
            $cx += ($x1 + $x2) * $cross;
            $cy += ($y1 + $y2) * $cross;
        }

        return [$cx / (6 * $area), $cy / (6 * $area)];
    }

    /** Un punto garantizado dentro del polígono (también para cóncavos). */
    public function interiorPoint(array $polygon): ?array
    {
        $polygon = $this->normalize($polygon);
        $centroid = $this->centroid($polygon);

        if ($this->containsStrictly($polygon, $centroid)) {
            return $centroid;
        }

        // Abanico de triángulos desde cada vértice: el centroide de algún
        // triángulo cae dentro de un polígono simple.
        $n = count($polygon);
        for ($i = 0; $i < $n; $i++) {
            for ($j = 1; $j < $n - 1; $j++) {
                $a = $polygon[$i];
                $b = $polygon[($i + $j) % $n];
                $c = $polygon[($i + $j + 1) % $n];
                $candidate = [($a[0] + $b[0] + $c[0]) / 3, ($a[1] + $b[1] + $c[1]) / 3];
                if ($this->containsStrictly($polygon, $candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    public function signedArea(array $polygon): float
    {
        $n = count($polygon);
        $sum = 0.0;
        for ($i = 0; $i < $n; $i++) {
            [$x1, $y1] = $polygon[$i];
            [$x2, $y2] = $polygon[($i + 1) % $n];
            $sum += $x1 * $y2 - $x2 * $y1;
        }

        return $sum / 2;
    }

    public function distance(array $a, array $b): float
    {
        return hypot($b[0] - $a[0], $b[1] - $a[1]);
    }

    public function distanceToSegment(array $p, array $a, array $b): float
    {
        return $this->distance($p, $this->projectOnSegment($p, $a, $b)[0]);
    }

    /**
     * Proyección de $p sobre el segmento a-b.
     *
     * @return array{0: array{0: float, 1: float}, 1: float} [punto, t ∈ [0,1]]
     */
    public function projectOnSegment(array $p, array $a, array $b): array
    {
        $dx = $b[0] - $a[0];
        $dy = $b[1] - $a[1];
        $len2 = $dx * $dx + $dy * $dy;

        if ($len2 < self::EPSILON) {
            return [[(float) $a[0], (float) $a[1]], 0.0];
        }

        $t = (($p[0] - $a[0]) * $dx + ($p[1] - $a[1]) * $dy) / $len2;
        $t = max(0.0, min(1.0, $t));

        return [[$a[0] + $t * $dx, $a[1] + $t * $dy], $t];
    }

    /** Cualquier contacto entre segmentos, incluidos extremos y colineales. */
    public function segmentsIntersect(array $p1, array $p2, array $q1, array $q2): bool
    {
        $d1 = $this->orientation($q1, $q2, $p1);
        $d2 = $this->orientation($q1, $q2, $p2);
        $d3 = $this->orientation($p1, $p2, $q1);
        $d4 = $this->orientation($p1, $p2, $q2);

        if ($d1 * $d2 < 0 && $d3 * $d4 < 0) {
            return true;
        }

        return ($d1 === 0 && $this->onSegment($q1, $q2, $p1))
            || ($d2 === 0 && $this->onSegment($q1, $q2, $p2))
            || ($d3 === 0 && $this->onSegment($p1, $p2, $q1))
            || ($d4 === 0 && $this->onSegment($p1, $p2, $q2));
    }

    /**
     * Puntos donde el segmento a-b cruza el borde del polígono, ordenados por t.
     *
     * @return list<array{0: array{0: float, 1: float}, 1: float}> [punto, t]
     */
    public function boundaryCrossings(array $polygon, array $a, array $b): array
    {
        $polygon = $this->normalize($polygon);
        $n = count($polygon);
        $hits = [];

        for ($i = 0; $i < $n; $i++) {
            $hit = $this->segmentIntersection($a, $b, $polygon[$i], $polygon[($i + 1) % $n]);
            if ($hit !== null) {
                $key = round($hit[1], 9);
                $hits[(string) $key] = $hit;
            }
        }

        ksort($hits, SORT_NUMERIC);

        return array_values($hits);
    }

    /**
     * Intersección puntual de p1-p2 con q1-q2 (no colineal).
     *
     * @return array{0: array{0: float, 1: float}, 1: float}|null [punto, t sobre p1-p2]
     */
    public function segmentIntersection(array $p1, array $p2, array $q1, array $q2): ?array
    {
        $rx = $p2[0] - $p1[0];
        $ry = $p2[1] - $p1[1];
        $sx = $q2[0] - $q1[0];
        $sy = $q2[1] - $q1[1];
        $den = $rx * $sy - $ry * $sx;

        if (abs($den) < self::EPSILON) {
            return null;
        }

        $t = (($q1[0] - $p1[0]) * $sy - ($q1[1] - $p1[1]) * $sx) / $den;
        $u = (($q1[0] - $p1[0]) * $ry - ($q1[1] - $p1[1]) * $rx) / $den;

        if ($t < -self::EPSILON || $t > 1 + self::EPSILON || $u < -self::EPSILON || $u > 1 + self::EPSILON) {
            return null;
        }

        return [[$p1[0] + $t * $rx, $p1[1] + $t * $ry], max(0.0, min(1.0, $t))];
    }

    /** Cruce en un punto interior de ambos segmentos (no solo contacto). */
    public function segmentsCrossProperly(array $p1, array $p2, array $q1, array $q2): bool
    {
        return $this->orientation($q1, $q2, $p1) * $this->orientation($q1, $q2, $p2) < 0
            && $this->orientation($p1, $p2, $q1) * $this->orientation($p1, $p2, $q2) < 0;
    }

    /** Normaliza a [[float, float], ...] y elimina el punto de cierre repetido. */
    public function normalize(array $polygon): array
    {
        $points = array_values(array_map(
            fn ($p) => [(float) ($p[0] ?? $p['x'] ?? 0), (float) ($p[1] ?? $p['y'] ?? 0)],
            $polygon,
        ));

        $n = count($points);
        if ($n > 1 && $this->distance($points[0], $points[$n - 1]) < self::EPSILON) {
            array_pop($points);
        }

        return $points;
    }

    private function probePoints(array $polygon): array
    {
        $points = $polygon;
        $n = count($polygon);
        for ($i = 0; $i < $n; $i++) {
            $a = $polygon[$i];
            $b = $polygon[($i + 1) % $n];
            $points[] = [($a[0] + $b[0]) / 2, ($a[1] + $b[1]) / 2];
        }

        if ($interior = $this->interiorPoint($polygon)) {
            $points[] = $interior;
        }

        return $points;
    }

    private function rayCast(array $polygon, array $point): bool
    {
        [$px, $py] = [(float) $point[0], (float) $point[1]];
        $inside = false;
        $n = count($polygon);

        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$xi, $yi] = $polygon[$i];
            [$xj, $yj] = $polygon[$j];

            if ((($yi > $py) !== ($yj > $py))
                && ($px < ($xj - $xi) * ($py - $yi) / (($yj - $yi) ?: self::EPSILON) + $xi)) {
                $inside = !$inside;
            }
        }

        return $inside;
    }

    private function boundingBoxesOverlap(array $a, array $b): bool
    {
        return min(array_column($a, 0)) < max(array_column($b, 0))
            && min(array_column($b, 0)) < max(array_column($a, 0))
            && min(array_column($a, 1)) < max(array_column($b, 1))
            && min(array_column($b, 1)) < max(array_column($a, 1));
    }

    private function orientation(array $a, array $b, array $c): int
    {
        $value = ($b[0] - $a[0]) * ($c[1] - $a[1]) - ($b[1] - $a[1]) * ($c[0] - $a[0]);

        if (abs($value) < self::EPSILON) {
            return 0;
        }

        return $value > 0 ? 1 : -1;
    }

    private function onSegment(array $a, array $b, array $p): bool
    {
        return $p[0] <= max($a[0], $b[0]) + self::EPSILON && $p[0] >= min($a[0], $b[0]) - self::EPSILON
            && $p[1] <= max($a[1], $b[1]) + self::EPSILON && $p[1] >= min($a[1], $b[1]) - self::EPSILON;
    }
}
