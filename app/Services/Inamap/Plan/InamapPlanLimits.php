<?php

namespace Domain\App\Services\Inamap\Plan;

use App\Exceptions\SubscriptionLimitExceededException;
use App\Models\Tenancy;
use App\Models\User;
use App\Services\Subscription\PlanLimitsService;
use Domain\App\Models\Inamap\Inamap;
use Domain\App\Models\Inamap\Layout;
use Domain\App\Models\Inamap\Location;
use Domain\App\Models\Inamap\RouteSession;

/**
 * Límites de plan propios del dominio.
 *
 * PlanLimitsService (core) lee los límites del plan pero solo sabe contar sus
 * propias claves: para una clave desconocida devuelve un conteo de 0, con lo
 * que `max_inamaps` nunca se haría cumplir. Aquí se reutiliza su lectura del
 * plan, su bypass de SystemAdmin y su excepción, y se cuenta en el dominio.
 * `max_tenants` sigue siendo responsabilidad del core.
 */
class InamapPlanLimits
{
    public const KEYS = ['max_inamaps', 'max_layouts_per_inamap', 'max_locations', 'max_active_route_sessions'];

    /** @throws SubscriptionLimitExceededException */
    public function enforce(?string $tenancyId, string $key, ?User $user = null, ?string $scopeId = null, int $adding = 1): void
    {
        $check = $this->check($tenancyId, $key, $user, $scopeId, $adding);

        if (!$check['allowed']) {
            throw new SubscriptionLimitExceededException($check['message'], $check);
        }
    }

    public function check(?string $tenancyId, string $key, ?User $user = null, ?string $scopeId = null, int $adding = 1): array
    {
        $tenancy = $tenancyId ? Tenancy::find($tenancyId) : null;

        if (!$tenancy || PlanLimitsService::isSystemAdmin($user)) {
            return ['allowed' => true, 'limit' => null, 'current' => null, 'key' => $key];
        }

        $limits = PlanLimitsService::getPlanLimits($tenancy);
        $limit = $limits[$key] ?? config("inamap.plan_limit_defaults.{$key}");

        if (PlanLimitsService::isUnlimited($limit === null ? null : (int) $limit)) {
            return ['allowed' => true, 'limit' => null, 'current' => null, 'key' => $key];
        }

        $current = $this->count($tenancy, $key, $scopeId);
        $allowed = $current + $adding <= (int) $limit;

        return [
            'allowed' => $allowed,
            'limit'   => (int) $limit,
            'current' => $current,
            'key'     => $key,
            'code'    => 'PLAN_LIMIT_REACHED',
            'message' => $allowed ? '' : __('inamap.errors.plan_limit', ['limit' => $limit, 'resource' => __("inamap.limits.{$key}")]),
        ];
    }

    private function count(Tenancy $tenancy, string $key, ?string $scopeId): int
    {
        return match ($key) {
            'max_inamaps'               => Inamap::where('tenancy_id', $tenancy->id)->count(),
            'max_layouts_per_inamap'    => Layout::where('inamap_id', $scopeId)->count(),
            'max_locations'             => Location::where('tenancy_id', $tenancy->id)->count(),
            'max_active_route_sessions' => RouteSession::where('tenant_id', $scopeId)
                ->where('status', RouteSession::STATUS_ACTIVE)
                ->where('expires_at', '>', now())
                ->count(),
            default => 0,
        };
    }
}
