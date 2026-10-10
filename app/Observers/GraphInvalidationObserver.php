<?php

namespace Domain\App\Observers;

use Domain\App\Services\Inamap\Routing\InamapGraphRepository;
use Illuminate\Database\Eloquent\Model;

/**
 * Invalida el grafo de rutas del inamap cuando cambian sus caminos, enlaces
 * o layouts. Al eliminar un camino también elimina sus enlaces: con borrado
 * lógico, la FK en cascada no se dispara.
 */
class GraphInvalidationObserver
{
    public function saved(Model $model): void
    {
        $this->forget($model);
    }

    public function deleted(Model $model): void
    {
        if ($model instanceof \Domain\App\Models\Inamap\Path) {
            \Domain\App\Models\Inamap\PathLink::query()
                ->where(fn ($q) => $q->where('from_path_id', $model->id)->orWhere('to_path_id', $model->id))
                ->delete();
        }

        $this->forget($model);
    }

    public function restored(Model $model): void
    {
        $this->forget($model);
    }

    private function forget(Model $model): void
    {
        if ($model->inamap_id) {
            InamapGraphRepository::forget($model->inamap_id);
        }
    }
}
