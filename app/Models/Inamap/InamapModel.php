<?php

namespace Domain\App\Models\Inamap;

use App\Models\User;
use Domain\App\Services\Inamap\Tenancy\TenantContext;
use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Base de todos los modelos INAMAP: PK UUID (S7), filtros react-admin,
 * borrado lógico y alcance por tenant/tenancy.
 */
abstract class InamapModel extends Model
{
    use HasUuids, Filterable, SoftDeletes;

    protected $guarded = [];

    /**
     * Limita la consulta a lo que el usuario puede ver (ver TenantContext):
     * SystemAdmin todo (o el tenant pedido), TenancyAdmin su tenancy (o el
     * tenant al que cambió, validado), el resto su tenant.
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        $table = $this->getTable();
        $context = app(TenantContext::class);
        $activeTenantId = $context->activeTenantId($user);

        if ($user->isSystemAdmin()) {
            return $activeTenantId ? $this->scopeToTenant($query, $activeTenantId) : $query;
        }

        if ($user->hasRole('TenancyAdmin')) {
            if ($activeTenantId) {
                return $context->tenantBelongsToUser($user, $activeTenantId)
                    ? $this->scopeToTenant($query, $activeTenantId)
                    : $query->whereRaw('1 = 0');
            }

            return $query->where("{$table}.tenancy_id", $user->tenancy_id);
        }

        return $activeTenantId ? $this->scopeToTenant($query, $activeTenantId) : $query->whereRaw('1 = 0');
    }

    /** Lo que ve un tenant. Los modelos con filas de alcance tenancy lo amplían. */
    protected function scopeToTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where($this->getTable() . '.tenant_id', $tenantId);
    }

    public function canBeManagedBy(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->isSystemAdmin()) {
            return true;
        }

        if ($user->hasRole('TenancyAdmin')) {
            return $this->tenancy_id !== null && (string) $user->tenancy_id === (string) $this->tenancy_id;
        }

        return $this->tenant_id !== null && (string) $user->tenant_id === (string) $this->tenant_id;
    }
}
