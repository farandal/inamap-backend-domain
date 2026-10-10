<?php

namespace Domain\App\Http\Controllers\API\Inamap;

use App\Models\User;
use Domain\App\Models\Inamap\InamapModel;

/**
 * Política común de los recursos INAMAP. El acceso por ruta lo decide el
 * middleware `access` (permisos por route_name, ver database/data); aquí solo
 * se verifica que la fila pertenezca al alcance del usuario.
 */
class InamapPolicy
{
    public function create(User $user, $item = null): bool
    {
        return $user->isSystemAdmin()
            || $user->hasRole('TenancyAdmin')
            || $user->hasRole('Tenant')
            || $user->hasRole('User');
    }

    public function view(User $user, ?InamapModel $item): bool
    {
        return $item !== null && $item->canBeManagedBy($user);
    }

    public function manage(User $user, ?InamapModel $item): bool
    {
        return $item !== null && $item->canBeManagedBy($user);
    }

    public function delete(User $user, ?InamapModel $item): bool
    {
        return $this->manage($user, $item);
    }
}
