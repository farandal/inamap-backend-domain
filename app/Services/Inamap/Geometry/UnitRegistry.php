<?php

namespace Domain\App\Services\Inamap\Geometry;

/**
 * Registro de unidades de medida (S2). v1 habilita solo metros; agregar una
 * unidad es agregar una entrada en config('inamap.units').
 */
class UnitRegistry
{
    /** @return list<string> */
    public function enabled(): array
    {
        return array_keys(array_filter(
            config('inamap.units', []),
            fn (array $unit) => (bool) ($unit['enabled'] ?? false),
        ));
    }

    public function isEnabled(string $unit): bool
    {
        return in_array($unit, $this->enabled(), true);
    }

    public function toMeters(string $unit): float
    {
        return (float) (config("inamap.units.{$unit}.to_meters") ?? 1.0) ?: 1.0;
    }

    /** Convierte metros a la unidad indicada (p. ej. la tolerancia de unión). */
    public function fromMeters(float $meters, string $unit): float
    {
        return $meters / $this->toMeters($unit);
    }
}
