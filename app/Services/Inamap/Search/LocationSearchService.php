<?php

namespace Domain\App\Services\Inamap\Search;

use Domain\App\Models\Inamap\Concerns\IsSearchable;
use Domain\App\Models\Inamap\Inamap;
use Illuminate\Support\Facades\DB;

/**
 * Búsqueda difusa sobre zonas, estructuras y ubicaciones de un inamap (RF-19).
 *
 * search_text ya está normalizado (minúsculas, sin acentos), así que la
 * consulta se normaliza igual y se compara con pg_trgm: similitud de palabra
 * para tolerar errores de tipeo + ILIKE para coincidencias parciales.
 */
class LocationSearchService
{
    private const SOURCES = [
        'zone'      => 'inamap_zones',
        'structure' => 'inamap_structures',
        'location'  => 'inamap_locations',
    ];

    /**
     * @return list<array{type: string, id: string, label: string, code: ?string, layout_id: string, category_id: ?string, score: float}>
     */
    public function search(Inamap $inamap, string $query, array $filters = []): array
    {
        $term = $this->normalize($query);
        $categoryIds = $this->categoryScope($filters['category'] ?? null);
        $limit = (int) min(
            max((int) ($filters['limit'] ?? config('inamap.search.default_limit', 20)), 1),
            (int) config('inamap.search.max_limit', 50),
        );

        if ($term === '' && $categoryIds === null) {
            return [];
        }

        $types = $categoryIds !== null ? ['location'] : array_keys(self::SOURCES);
        if (!empty($filters['type']) && isset(self::SOURCES[$filters['type']])) {
            $types = array_values(array_intersect($types, [$filters['type']]));
        }

        $pgsql = DB::getDriverName() === 'pgsql';
        $threshold = (float) config('inamap.search.similarity_threshold', 0.2);
        $selects = [];
        $bindings = [];

        foreach ($types as $type) {
            $table = self::SOURCES[$type];
            $categoryColumn = $type === 'location' ? 'category_id' : 'NULL';
            $score = $term === ''
                ? '1.0'
                : ($pgsql ? 'GREATEST(word_similarity(?, search_text), CASE WHEN search_text LIKE ? THEN 1.0 ELSE 0 END)' : 'CASE WHEN search_text LIKE ? THEN 1.0 ELSE 0 END');

            $sql = "SELECT '{$type}' AS type, id, label, code, layout_id, {$categoryColumn} AS category_id, {$score} AS score
                    FROM {$table}
                    WHERE inamap_id = ? AND deleted_at IS NULL";

            if ($term !== '') {
                $bindings = [...$bindings, ...($pgsql ? [$term, "%{$term}%"] : ["%{$term}%"])];
            }
            $bindings[] = $inamap->id;

            if ($term !== '') {
                $sql .= $pgsql ? ' AND (search_text LIKE ? OR word_similarity(?, search_text) >= ?)' : ' AND search_text LIKE ?';
                $bindings = [...$bindings, ...($pgsql ? ["%{$term}%", $term, $threshold] : ["%{$term}%"])];
            }

            if ($type === 'location' && $categoryIds !== null) {
                $sql .= ' AND category_id IN (' . implode(',', array_fill(0, count($categoryIds), '?')) . ')';
                $bindings = [...$bindings, ...$categoryIds];
            }

            if (!empty($filters['layout'])) {
                $sql .= ' AND layout_id = ?';
                $bindings[] = $filters['layout'];
            }

            $selects[] = "({$sql})";
        }

        if (!$selects) {
            return [];
        }

        $rows = DB::select(
            'SELECT * FROM (' . implode(' UNION ALL ', $selects) . ') AS hits ORDER BY score DESC, label ASC LIMIT ' . $limit,
            $bindings,
        );

        return array_map(fn ($row) => [
            'type'        => $row->type,
            'id'          => $row->id,
            'label'       => $row->label,
            'code'        => $row->code,
            'layout_id'   => $row->layout_id,
            'category_id' => $row->category_id,
            'score'       => round((float) $row->score, 4),
        ], $rows);
    }

    public function normalize(string $query): string
    {
        return mb_substr(IsSearchable::normalizeSearchText([$query]), 0, 120);
    }

    /** Una categoría incluye sus subcategorías. Null = sin filtro. */
    private function categoryScope(?string $categoryId): ?array
    {
        if (!$categoryId || !\Illuminate\Support\Str::isUuid($categoryId)) {
            return null;
        }

        $ids = [$categoryId];
        $frontier = [$categoryId];
        while ($frontier) {
            $frontier = DB::table('inamap_categories')
                ->whereIn('category_id', $frontier)
                ->whereNull('deleted_at')
                ->pluck('id')
                ->diff($ids)
                ->values()
                ->all();
            $ids = [...$ids, ...$frontier];
        }

        return $ids;
    }
}
