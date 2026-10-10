<?php

namespace Domain\App\Services\Inamap\Session;

use App\Models\User;
use Domain\App\Models\Inamap\Inamap;
use Domain\App\Models\Inamap\RouteSession;
use Domain\App\Services\Inamap\Plan\InamapPlanLimits;
use Domain\App\Services\Inamap\Qr\QrService;
use Domain\App\Services\Inamap\Routing\RouteService;

/**
 * Sesiones de ruta (S4, README §3.6): calcula una ruta, la congela y la expone
 * mediante un token opaco de 256 bits y un QR. El token solo se devuelve en
 * claro al crear la sesión; en BD se guarda su SHA-256.
 */
class RouteSessionService
{
    public function __construct(
        private readonly RouteService $routes,
        private readonly QrService $qr,
        private readonly InamapPlanLimits $limits,
    ) {
    }

    /**
     * @return array{session: RouteSession, token: string, view_url: string, qr_svg: string}
     */
    public function create(Inamap $inamap, string $from, string $to, bool $accessible, string $createdBy, ?User $user = null): array
    {
        $this->limits->enforce($inamap->tenancy_id, 'max_active_route_sessions', $user, $inamap->tenant_id);

        $route = $this->routes->route($inamap, $from, $to, $accessible);
        $token = $this->newToken();

        $session = RouteSession::create([
            'tenancy_id'         => $inamap->tenancy_id,
            'tenant_id'          => $inamap->tenant_id,
            'inamap_id'          => $inamap->id,
            'created_by_user_id' => $user?->id,
            'created_by'         => $createdBy,
            'from_ref'           => $from,
            'to_ref'             => $to,
            'accessible'         => $accessible,
            'route'              => $route,
            'token_hash'         => RouteSession::hashToken($token),
            'expires_at'         => now()->addMinutes($inamap->sessionTtlMinutes()),
            'status'             => RouteSession::STATUS_ACTIVE,
        ]);

        $viewUrl = $this->viewUrl($inamap, $token);

        return [
            'session'  => $session,
            'token'    => $token,
            'view_url' => $viewUrl,
            'qr_svg'   => $this->qr->svg($viewUrl),
        ];
    }

    /** Sesión vigente para un token, o null si no existe/expiró/fue revocada. */
    public function findByToken(?string $token): ?RouteSession
    {
        if (!$token || strlen($token) < 32) {
            return null;
        }

        return RouteSession::query()->where('token_hash', RouteSession::hashToken($token))->first();
    }

    public function markOpened(RouteSession $session): void
    {
        $session->forceFill([
            'first_opened_at' => $session->first_opened_at ?? now(),
            'open_count'      => $session->open_count + 1,
        ])->save();
    }

    /** El token viaja en el fragmento (#t=) para no quedar en logs de acceso. */
    public function viewUrl(Inamap $inamap, string $token): string
    {
        return rtrim((string) config('inamap.route_sessions.app_url'), '/') . "/inamap/{$inamap->id}#t={$token}";
    }

    public function pruneExpired(): int
    {
        return RouteSession::query()->where('expires_at', '<', now()->subDay())->delete();
    }

    private function newToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
