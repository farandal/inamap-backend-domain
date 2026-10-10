<?php

namespace Domain\App\Http\Controllers\API\Inamap;

use EloquentFilter\ModelFilter;

/**
 * Filtros react-admin comunes a los recursos INAMAP.
 */
class InamapFilter extends ModelFilter
{
    protected $drop_id = false;

    public $relations = [];

    /** Columna usada por la búsqueda libre `q`. */
    protected string $searchColumn = 'label';

    public function q($value)
    {
        $value = trim((string) $value);

        return $this->where(fn ($q) => $q
            ->where($this->searchColumn, 'ILIKE', "%{$value}%")
            ->when($this->searchColumn === 'label', fn ($q) => $q->orWhere('code', 'ILIKE', "%{$value}%")));
    }

    public function id($id)
    {
        return is_array($id) ? $this->whereIn('id', $id) : $this->where('id', $id);
    }

    public function ids($ids)
    {
        return $this->whereIn('id', (array) $ids);
    }

    public function inamapId($id)
    {
        return $this->where('inamap_id', $id);
    }

    public function layoutId($id)
    {
        return $this->where('layout_id', $id);
    }

    public function tenantId($id)
    {
        return $this->where('tenant_id', $id);
    }

    public function code($code)
    {
        return $this->where('code', 'ILIKE', "%{$code}%");
    }

    public function label($label)
    {
        return $this->where('label', 'ILIKE', "%{$label}%");
    }
}
