<?php

namespace Domain\App\Rules;

use Closure;
use Domain\App\Services\Inamap\Geometry\PolygonService;
use Illuminate\Contracts\Validation\ValidationRule;

/** Polígono con ≥ 3 vértices, área no nula y sin autointersecciones. */
class PolygonIsSimple implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_array($value) || !$this->wellFormed($value) || !(new PolygonService())->isSimple($value)) {
            $fail(__('inamap.errors.polygon_not_simple'));
        }
    }

    private function wellFormed(array $value): bool
    {
        foreach ($value as $point) {
            if (!is_array($point) || count($point) !== 2 || !is_numeric($point[0] ?? null) || !is_numeric($point[1] ?? null)) {
                return false;
            }
        }

        return true;
    }
}
