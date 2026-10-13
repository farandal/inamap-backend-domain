<?php

namespace Domain\App\Http\Controllers\API\Inamap;

use Domain\App\Models\Inamap\Layout;
use Domain\App\Services\Inamap\Geometry\GeometryValidator;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * CRUD de zonas y estructuras: misma forma, distinta regla de solapamiento.
 */
abstract class PolygonController extends InamapBaseController
{
    /** true = el solapamiento bloquea (zonas); false = solo advierte (estructuras). */
    protected bool $overlapIsError = false;

    public function _create($request)
    {
        $this->_authorize('create');
        $validated = app($this->requestValidator)->validated();
        $owner = $this->ownership($request, $validated);
        $layout = Layout::find($validated['layout_id']);

        $warnings = $this->checkGeometry($layout, null, $validated);
        $item = $this->_model::create(array_merge($validated, $owner));

        return $this->afterSave($request, $item->fresh(), ['warnings' => $warnings]);
    }

    public function _update($request, $id, $item)
    {
        $item = $this->found($item);
        $this->_authorize('manage', $item);

        $validated = app($this->requestValidator)->validated();
        unset($validated['tenant_id'], $validated['tenancy_id'], $validated['inamap_id']);

        // Mover a otro layout: debe ser del mismo inamap.
        if (isset($validated['layout_id']) && $validated['layout_id'] !== $item->layout_id) {
            $target = $this->ownedLayout($request, $validated['layout_id']);
            if ($target->inamap_id !== $item->inamap_id) {
                throw ValidationException::withMessages(['layout_id' => __('inamap.errors.layout_not_found')]);
            }
        }

        $layout = Layout::find($validated['layout_id'] ?? $item->layout_id);
        $warnings = $this->checkGeometry($layout, $item->id, array_merge($item->only(['label', 'polygon']), $validated));

        $item->update($validated);

        return $this->afterSave($request, $item->fresh(), ['warnings' => $warnings]);
    }

    private function checkGeometry(Layout $layout, ?string $id, array $data): array
    {
        $validator = app(GeometryValidator::class);
        $candidate = ['x' => ['id' => $id, 'label' => $data['label'] ?? null, 'polygon' => $data['polygon'] ?? []]];
        $warnings = [];

        // Las zonas solapadas se rechazan salvo que el inamap lo permita en su configuración
        // (settings.allow_zone_overlap, apagado por defecto).
        if ($this->overlapIsError) {
            $conflicts = $this->zoneOverlapAllowed($layout) ? [] : ($validator->zoneConflicts($layout, $candidate)['x'] ?? []);
            if ($conflicts) {
                throw ValidationException::withMessages([
                    'polygon' => __('inamap.errors.zone_overlap', ['zones' => implode(', ', array_column($conflicts, 'label'))]),
                ]);
            }
        } else {
            $warnings = $validator->structureWarnings($layout, $candidate);
        }

        if (!$validator->insideLayout($layout, $data['polygon'] ?? [])) {
            $warnings[] = ['code' => 'OUTSIDE_LAYOUT', 'item' => $id, 'message' => __('inamap.warnings.outside_layout')];
        }

        return $warnings;
    }

    private function zoneOverlapAllowed(Layout $layout): bool
    {
        return (bool) $layout->inamap?->allowsZoneOverlap();
    }
}
