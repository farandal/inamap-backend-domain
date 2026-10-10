<?php

namespace Domain\App\Services\Inamap\Geometry;

use Domain\App\Models\Inamap\Layout;
use Domain\App\Models\Inamap\Structure;
use Domain\App\Models\Inamap\Zone;

/**
 * Reglas de integridad geométrica de un layout (README §7.5):
 * - zona ↔ zona: no pueden solaparse (error).
 * - estructura ↔ estructura: solapamiento permitido con advertencia (S1).
 * - estructura ↔ zona: permitido (capas distintas).
 * - geometría fuera del rectángulo del layout: advertencia.
 */
class GeometryValidator
{
    public function __construct(private readonly PolygonService $geometry = new PolygonService())
    {
    }

    /**
     * Conflictos de zonas. $candidates: [key => ['id' => ?string, 'polygon' => array]].
     * Las zonas existentes del layout que no estén en $candidates y no figuren
     * en $removedIds también participan.
     *
     * @return array<string|int, list<array{id: ?string, label: ?string}>> key del candidato => zonas con las que choca
     */
    public function zoneConflicts(Layout $layout, array $candidates, array $removedIds = []): array
    {
        $editedIds = array_filter(array_column($candidates, 'id'));
        $existing = Zone::query()
            ->where('layout_id', $layout->id)
            ->whereNotIn('id', [...$editedIds, ...$removedIds])
            ->get(['id', 'label', 'polygon']);

        $conflicts = [];
        $keys = array_keys($candidates);

        foreach ($keys as $i => $key) {
            $polygon = $candidates[$key]['polygon'] ?? [];

            foreach ($existing as $zone) {
                if ($this->geometry->overlaps($polygon, $zone->polygon ?? [])) {
                    $conflicts[$key][] = ['id' => $zone->id, 'label' => $zone->label];
                }
            }

            foreach (array_slice($keys, $i + 1) as $otherKey) {
                if ($this->geometry->overlaps($polygon, $candidates[$otherKey]['polygon'] ?? [])) {
                    $conflicts[$key][] = ['id' => $candidates[$otherKey]['id'] ?? null, 'label' => $candidates[$otherKey]['label'] ?? null];
                    $conflicts[$otherKey][] = ['id' => $candidates[$key]['id'] ?? null, 'label' => $candidates[$key]['label'] ?? null];
                }
            }
        }

        return $conflicts;
    }

    /** Advertencias (no bloqueantes) de estructuras que se solapan entre sí. */
    public function structureWarnings(Layout $layout, array $candidates, array $removedIds = []): array
    {
        $editedIds = array_filter(array_column($candidates, 'id'));
        $existing = Structure::query()
            ->where('layout_id', $layout->id)
            ->whereNotIn('id', [...$editedIds, ...$removedIds])
            ->get(['id', 'label', 'polygon']);

        $warnings = [];
        $keys = array_keys($candidates);

        foreach ($keys as $i => $key) {
            $polygon = $candidates[$key]['polygon'] ?? [];
            $others = [
                ...$existing->map(fn ($s) => ['id' => $s->id, 'label' => $s->label, 'polygon' => $s->polygon])->all(),
                ...array_map(fn ($k) => $candidates[$k], array_slice($keys, $i + 1)),
            ];

            foreach ($others as $other) {
                if ($this->geometry->overlaps($polygon, $other['polygon'] ?? [])) {
                    $warnings[] = [
                        'code'    => 'STRUCTURE_OVERLAP',
                        'item'    => $candidates[$key]['id'] ?? $candidates[$key]['client_id'] ?? $key,
                        'with'    => $other['id'] ?? $other['client_id'] ?? null,
                        'message' => __('inamap.warnings.structure_overlap', ['a' => $candidates[$key]['label'] ?? '', 'b' => $other['label'] ?? '']),
                    ];
                }
            }
        }

        return $warnings;
    }

    /** ¿Todos los puntos caen dentro del rectángulo [0,width]×[0,height]? */
    public function insideLayout(Layout $layout, array $points): bool
    {
        foreach ($points as $point) {
            $x = (float) ($point[0] ?? 0);
            $y = (float) ($point[1] ?? 0);
            if ($x < 0 || $y < 0 || $x > $layout->width || $y > $layout->height) {
                return false;
            }
        }

        return true;
    }
}
