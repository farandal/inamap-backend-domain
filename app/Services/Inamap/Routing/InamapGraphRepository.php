<?php

namespace Domain\App\Services\Inamap\Routing;

use Domain\App\Models\Inamap\Inamap;
use Domain\App\Models\Inamap\Path;
use Domain\App\Models\Inamap\PathLink;
use Domain\App\Services\Inamap\Geometry\UnitRegistry;
use Illuminate\Support\Facades\Cache;

/**
 * Construye y cachea el grafo de rutas de un inamap. GraphInvalidationObserver
 * llama a forget() ante cualquier cambio en caminos, enlaces o layouts.
 */
class InamapGraphRepository
{
    public function __construct(
        private readonly PathGraphBuilder $builder,
        private readonly UnitRegistry $units,
    ) {
    }

    public function for(Inamap $inamap): Graph
    {
        $data = Cache::remember(
            self::cacheKey($inamap->id),
            (int) config('inamap.graph_cache_ttl', 3600),
            fn () => $this->build($inamap)->toArray(),
        );

        return Graph::fromArray($data);
    }

    public function build(Inamap $inamap): Graph
    {
        $paths = Path::query()
            ->where('inamap_id', $inamap->id)
            ->get(['id', 'layout_id', 'points', 'direction'])
            ->map(fn (Path $path) => [
                'id'        => $path->id,
                'layout_id' => $path->layout_id,
                'points'    => $path->points ?? [],
                'direction' => $path->direction,
            ])
            ->all();

        $links = PathLink::query()
            ->where('inamap_id', $inamap->id)
            ->get()
            ->map(fn (PathLink $link) => $link->only([
                'id', 'from_path_id', 'from_end', 'to_path_id', 'to_end', 'kind', 'vertical_type', 'cost', 'bidirectional',
            ]))
            ->all();

        return $this->builder->build($paths, $links, $this->options($inamap));
    }

    /** @return array{tolerance: float, to_meters: float, vertical_types: array} */
    public function options(Inamap $inamap): array
    {
        return [
            'tolerance'      => $this->tolerance($inamap),
            'to_meters'      => $this->units->toMeters($inamap->unit),
            'vertical_types' => config('inamap.vertical_types', []),
        ];
    }

    public function tolerance(Inamap $inamap): float
    {
        return $this->units->fromMeters((float) config('inamap.snap_tolerance_m', 0.3), $inamap->unit);
    }

    public static function forget(string $inamapId): void
    {
        Cache::forget(self::cacheKey($inamapId));
    }

    public static function cacheKey(string $inamapId): string
    {
        return "inamap:graph:{$inamapId}";
    }
}
