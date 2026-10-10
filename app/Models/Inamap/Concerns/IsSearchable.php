<?php

namespace Domain\App\Models\Inamap\Concerns;

use Illuminate\Support\Str;

/**
 * Mantiene `search_text` normalizado (minúsculas, sin acentos) con etiqueta,
 * código, términos y, en ubicaciones, la categoría. Ver LocationSearchService.
 */
trait IsSearchable
{
    public static function bootIsSearchable(): void
    {
        static::saving(function ($model) {
            $model->search_text = static::normalizeSearchText($model->searchableParts());
        });
    }

    protected function searchableParts(): array
    {
        return [$this->label, $this->code, ...array_values((array) ($this->terms ?? []))];
    }

    public static function normalizeSearchText(array $parts): string
    {
        $text = implode(' ', array_filter(array_map(
            fn ($part) => is_scalar($part) ? (string) $part : null,
            $parts,
        )));

        return trim(preg_replace('/\s+/', ' ', Str::lower(Str::ascii($text))));
    }
}
