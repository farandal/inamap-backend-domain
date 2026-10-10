<?php

namespace Domain\App\Http\Controllers\API\Inamap;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base de los FormRequest INAMAP: en creación los campos son obligatorios,
 * en actualización solo se validan los presentes (`sometimes`).
 */
abstract class InamapRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function isCreate(): bool
    {
        return $this->isMethod('post') && !$this->route('id');
    }

    /** `required` al crear, `sometimes|required` al actualizar. */
    protected function req(): string
    {
        return $this->isCreate() ? 'required' : 'sometimes|required';
    }

    /** Reglas comunes de términos de búsqueda y metadata. */
    protected function searchableRules(): array
    {
        return [
            'terms'    => 'nullable|array|max:50',
            'terms.*'  => 'string|max:100',
            'metadata' => 'nullable|array',
        ];
    }

    /** Polígono [[x, y], ...] con al menos 3 vértices. */
    protected function polygonRules(string $field = 'polygon'): array
    {
        return [
            $field          => [...explode('|', $this->req()), 'array', 'min:3', 'max:2000', new \Domain\App\Rules\PolygonIsSimple()],
            "{$field}.*"    => 'array|size:2',
            "{$field}.*.*"  => 'numeric',
        ];
    }
}
