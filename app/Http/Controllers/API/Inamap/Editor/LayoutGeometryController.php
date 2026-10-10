<?php

namespace Domain\App\Http\Controllers\API\Inamap\Editor;

use App\Http\Controllers\Controller;
use App\Support\ResponseHandler;
use Domain\App\Http\Controllers\API\Inamap\Layout\LayoutResource;
use Domain\App\Http\Controllers\API\Inamap\Location\LocationResource;
use Domain\App\Http\Controllers\API\Inamap\Path\PathResource;
use Domain\App\Http\Controllers\API\Inamap\Structure\StructureResource;
use Domain\App\Http\Controllers\API\Inamap\Zone\ZoneResource;
use Domain\App\Models\Inamap\Category;
use Domain\App\Models\Inamap\Layout;
use Domain\App\Models\Inamap\Location;
use Domain\App\Models\Inamap\Path;
use Domain\App\Models\Inamap\Structure;
use Domain\App\Models\Inamap\Zone;
use Domain\App\Rules\PolygonIsSimple;
use Domain\App\Services\Inamap\Geometry\GeometryValidator;
use Domain\App\Services\Inamap\Plan\InamapPlanLimits;
use Domain\App\Services\Inamap\Routing\InamapGraphRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Documento de geometría de un layout para el editor de canvas (RF-28).
 *
 * GET  inamap/layouts/{id}/geometry  → layout + zonas + estructuras + ubicaciones + caminos.
 * PUT  inamap/layouts/{id}/geometry  → guarda en UNA transacción. Por cada
 *      colección presente en el cuerpo: los ítems con `id` se actualizan, los
 *      que traen `client_id` (sin id) se crean y los existentes que no vienen
 *      se eliminan. Las colecciones ausentes no se tocan. Si algo falla, nada
 *      se aplica.
 *
 * La respuesta incluye `id_map` (client_id → id) para que el editor
 * reemplace sus identificadores temporales, y `warnings` no bloqueantes.
 */
class LayoutGeometryController extends Controller
{
    private const COLLECTIONS = ['zones', 'structures', 'locations', 'paths'];

    public function show(Request $request, string $id)
    {
        $layout = Layout::query()->visibleTo($request->user())->findOrFail($id);

        return ResponseHandler::json(['data' => $this->document($layout)]);
    }

    public function update(Request $request, string $id)
    {
        $layout = Layout::query()->visibleTo($request->user())->findOrFail($id);
        if (!$layout->canBeManagedBy($request->user())) {
            abort(403);
        }

        $payload = $request->only(self::COLLECTIONS);
        $this->validatePayload($payload);

        $validator = app(GeometryValidator::class);
        $warnings = [];
        $errors = [];

        $existing = [
            'zones'      => Zone::where('layout_id', $layout->id)->pluck('id')->all(),
            'structures' => Structure::where('layout_id', $layout->id)->pluck('id')->all(),
            'locations'  => Location::where('layout_id', $layout->id)->pluck('id')->all(),
            'paths'      => Path::where('layout_id', $layout->id)->pluck('id')->all(),
        ];

        // Ids ajenos al layout no se aceptan (ni de otros layouts ni inventados).
        foreach ($payload as $collection => $items) {
            foreach ($items as $index => $item) {
                if (!empty($item['id']) && !in_array($item['id'], $existing[$collection], true)) {
                    $errors["{$collection}.{$index}.id"] = __('inamap.errors.item_not_in_layout');
                }
            }
        }

        if (array_key_exists('zones', $payload)) {
            $removed = array_diff($existing['zones'], array_filter(array_column($payload['zones'], 'id')));
            // Al reemplazar la colección completa, las zonas existentes no
            // enviadas se eliminan y no cuentan para el solapamiento.
            foreach ($validator->zoneConflicts($layout, $payload['zones'], array_values($removed)) as $index => $conflicts) {
                $errors["zones.{$index}.polygon"] = __('inamap.errors.zone_overlap', [
                    'zones' => implode(', ', array_filter(array_column($conflicts, 'label'))),
                ]);
            }
        }

        if (array_key_exists('structures', $payload)) {
            $removed = array_diff($existing['structures'], array_filter(array_column($payload['structures'], 'id')));
            $warnings = [...$warnings, ...$validator->structureWarnings($layout, $payload['structures'], array_values($removed))];
        }

        $this->checkLocationReferences($request, $layout, $payload, $errors);

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        foreach ($payload as $collection => $items) {
            foreach ($items as $index => $item) {
                $points = $collection === 'locations' ? [[$item['x'], $item['y']]] : ($item['polygon'] ?? $item['points'] ?? []);
                if (!$validator->insideLayout($layout, $points)) {
                    $warnings[] = ['code' => 'OUTSIDE_LAYOUT', 'item' => $item['id'] ?? $item['client_id'] ?? "{$collection}.{$index}", 'message' => __('inamap.warnings.outside_layout')];
                }
            }
        }

        if (array_key_exists('locations', $payload)) {
            $new = count(array_filter($payload['locations'], fn ($item) => empty($item['id'])));
            $deleted = count(array_diff($existing['locations'], array_filter(array_column($payload['locations'], 'id'))));
            if ($new > $deleted) {
                app(InamapPlanLimits::class)->enforce($layout->tenancy_id, 'max_locations', $request->user(), null, $new - $deleted);
            }
        }

        $idMap = [];
        DB::transaction(function () use ($layout, $payload, $existing, &$idMap) {
            $owner = [
                'tenant_id'  => $layout->tenant_id,
                'tenancy_id' => $layout->tenancy_id,
                'inamap_id'  => $layout->inamap_id,
                'layout_id'  => $layout->id,
            ];

            // Estructuras antes que ubicaciones: una ubicación nueva puede
            // referirse a una estructura nueva por su client_id.
            foreach (['zones' => Zone::class, 'structures' => Structure::class, 'paths' => Path::class, 'locations' => Location::class] as $collection => $class) {
                if (!array_key_exists($collection, $payload)) {
                    continue;
                }

                $kept = [];
                foreach ($payload[$collection] as $item) {
                    $attributes = $this->attributes($collection, $item, $idMap);

                    if (!empty($item['id'])) {
                        $class::whereKey($item['id'])->firstOrFail()->update($attributes);
                        $kept[] = $item['id'];
                    } else {
                        $model = $class::create(array_merge($attributes, $owner));
                        $kept[] = $model->id;
                        if (!empty($item['client_id'])) {
                            $idMap[(string) $item['client_id']] = $model->id;
                        }
                    }
                }

                $class::query()->whereIn('id', array_diff($existing[$collection], $kept))->get()->each->delete();
            }
        });

        InamapGraphRepository::forget($layout->inamap_id);

        return ResponseHandler::json([
            'data'     => $this->document($layout->fresh()),
            'id_map'   => (object) $idMap,
            'warnings' => $warnings,
        ]);
    }

    private function validatePayload(array $payload): void
    {
        $rules = [];
        foreach (self::COLLECTIONS as $collection) {
            $rules[$collection] = 'sometimes|array|max:5000';
            $rules["{$collection}.*.id"] = 'nullable|uuid';
            $rules["{$collection}.*.client_id"] = 'nullable|string|max:64';
            $rules["{$collection}.*.metadata"] = 'nullable|array';
        }

        foreach (['zones', 'structures'] as $collection) {
            $rules["{$collection}.*.label"] = 'required|string|max:255';
            $rules["{$collection}.*.code"] = 'nullable|string|max:64';
            $rules["{$collection}.*.color"] = 'nullable|string|max:16';
            $rules["{$collection}.*.polygon"] = ['required', 'array', 'min:3', new PolygonIsSimple()];
            $rules["{$collection}.*.terms"] = 'nullable|array';
            $rules["{$collection}.*.terms.*"] = 'string|max:100';
        }

        $rules += [
            'locations.*.label'               => 'required|string|max:255',
            'locations.*.code'                => 'nullable|string|max:64',
            'locations.*.x'                   => 'required|numeric',
            'locations.*.y'                   => 'required|numeric',
            'locations.*.icon'                => 'nullable|string|max:64',
            'locations.*.qr_url'              => 'nullable|url|max:2048',
            'locations.*.category_id'         => 'nullable|uuid',
            'locations.*.structure_id'        => 'nullable|uuid',
            'locations.*.structure_client_id' => 'nullable|string|max:64',
            'locations.*.terms'               => 'nullable|array',
            'locations.*.terms.*'             => 'string|max:100',
            'paths.*.label'                   => 'nullable|string|max:255',
            'paths.*.points'                  => 'required|array|min:2',
            'paths.*.points.*'                => 'array|size:2',
            'paths.*.points.*.*'              => 'numeric',
            'paths.*.direction'               => ['sometimes', Rule::in(Path::DIRECTIONS)],
        ];

        Validator::make($payload, $rules)->validate();
    }

    private function checkLocationReferences(Request $request, Layout $layout, array $payload, array &$errors): void
    {
        if (!array_key_exists('locations', $payload)) {
            return;
        }

        $structureIds = array_key_exists('structures', $payload)
            ? array_filter(array_column($payload['structures'], 'id'))
            : Structure::where('layout_id', $layout->id)->pluck('id')->all();
        $structureClientIds = array_filter(array_column($payload['structures'] ?? [], 'client_id'));
        $categoryIds = Category::query()->visibleTo($request->user())->pluck('id')->all();

        foreach ($payload['locations'] as $index => $item) {
            if (!empty($item['structure_id']) && !in_array($item['structure_id'], $structureIds, true)) {
                $errors["locations.{$index}.structure_id"] = __('inamap.errors.structure_not_in_layout');
            }
            if (!empty($item['structure_client_id']) && !in_array($item['structure_client_id'], $structureClientIds, true)) {
                $errors["locations.{$index}.structure_client_id"] = __('inamap.errors.structure_not_in_layout');
            }
            if (!empty($item['category_id']) && !in_array($item['category_id'], $categoryIds, true)) {
                $errors["locations.{$index}.category_id"] = __('inamap.errors.category_not_found');
            }
        }
    }

    private function attributes(string $collection, array $item, array $idMap): array
    {
        return match ($collection) {
            'zones', 'structures' => [
                'code'     => $item['code'] ?? null,
                'label'    => $item['label'],
                'color'    => $item['color'] ?? null,
                'polygon'  => $item['polygon'],
                'terms'    => $item['terms'] ?? [],
                'metadata' => $item['metadata'] ?? null,
            ],
            'locations' => [
                'code'         => $item['code'] ?? null,
                'label'        => $item['label'],
                'x'            => $item['x'],
                'y'            => $item['y'],
                'icon'         => $item['icon'] ?? null,
                'qr_url'       => $item['qr_url'] ?? null,
                'category_id'  => $item['category_id'] ?? null,
                'structure_id' => $item['structure_id']
                    ?? (!empty($item['structure_client_id']) ? ($idMap[$item['structure_client_id']] ?? null) : null),
                'terms'        => $item['terms'] ?? [],
                'metadata'     => $item['metadata'] ?? null,
            ],
            'paths' => [
                'label'     => $item['label'] ?? null,
                'points'    => $item['points'],
                'direction' => $item['direction'] ?? 'both',
                'metadata'  => $item['metadata'] ?? null,
            ],
        };
    }

    private function document(Layout $layout): array
    {
        return [
            'layout'     => LayoutResource::make($layout),
            'zones'      => ZoneResource::collection(Zone::where('layout_id', $layout->id)->orderBy('label')->get()),
            'structures' => StructureResource::collection(Structure::where('layout_id', $layout->id)->orderBy('label')->get()),
            'locations'  => LocationResource::collection(Location::where('layout_id', $layout->id)->orderBy('label')->get()),
            'paths'      => PathResource::collection(Path::where('layout_id', $layout->id)->get()),
        ];
    }
}
