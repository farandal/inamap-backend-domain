<?php

namespace Domain\App\Services\Inamap\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Resuelve el tenant sobre el que actúa un usuario (mismo criterio que el
 * resto del framework):
 *
 * - SystemAdmin: `X-Tenant-Id` o `tenant_id` del request; sin él, ve todo.
 * - TenancyAdmin: `X-Tenant-Id` (TenantSwitcher) validado contra su tenancy.
 *   Un encabezado ajeno a su tenancy nunca amplía la visibilidad: falla cerrado.
 * - Tenant / User: siempre su propio tenant.
 */
class TenantContext
{
    /** Tenant activo para lecturas, o null = sin acotar a un tenant. */
    public function activeTenantId(User $user): ?string
    {
        if ($user->isSystemAdmin()) {
            return request()->header('X-Tenant-Id') ?: (request()->input('tenant_id') ?: null);
        }

        if ($user->hasRole('TenancyAdmin')) {
            $header = request()->header('X-Tenant-Id');

            return $header ? (string) $header : null;
        }

        return $user->tenant_id ? (string) $user->tenant_id : null;
    }

    public function tenantBelongsToUser(User $user, string $tenantId): bool
    {
        if ($user->isSystemAdmin()) {
            return Tenant::whereKey($tenantId)->exists();
        }

        if ($user->hasRole('TenancyAdmin')) {
            return Tenant::whereKey($tenantId)->where('tenancy_id', $user->tenancy_id)->exists();
        }

        return (string) $user->tenant_id === $tenantId;
    }

    /**
     * Tenant y tenancy donde se crea un recurso nuevo.
     *
     * @return array{tenant_id: string, tenancy_id: ?string}
     *
     * @throws ValidationException
     */
    public function forWrite(User $user, ?string $requestedTenantId = null): array
    {
        $tenantId = $user->hasRole('Tenant') || $user->hasRole('User')
            ? ($user->tenant_id ? (string) $user->tenant_id : null)
            : ($requestedTenantId ?: $this->activeTenantId($user));

        if (!$tenantId || !$this->tenantBelongsToUser($user, $tenantId)) {
            throw ValidationException::withMessages([
                'tenant_id' => __('inamap.errors.tenant_required'),
            ]);
        }

        return [
            'tenant_id'  => $tenantId,
            'tenancy_id' => Tenant::whereKey($tenantId)->value('tenancy_id'),
        ];
    }
}
