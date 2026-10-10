<?php

namespace Domain\App\Http\Middleware;

use Closure;
use Domain\App\Models\Inamap\Inamap;
use Domain\App\Services\Inamap\Session\RouteSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Puerta de la API pública de un inamap (README §3.6).
 *
 * Resuelve el inamap desde la ruta (`id`) o el query (`inamap`), exige que
 * esté publicado y, si su `access_mode` es `token_required`, valida que el
 * `X-Inamap-Token` corresponda a una sesión vigente de ESE inamap, o que
 * `X-Inamap-Access-Key` sea la credencial de dispositivo del inamap (kioscos).
 * Deja el inamap (y la sesión, si hay token) en los atributos del request.
 */
class InamapAccessToken
{
    public function __construct(private readonly RouteSessionService $sessions)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $id = $request->route('id') ?? $request->input('inamap');
        $inamap = is_string($id) && Str::isUuid($id) ? Inamap::find($id) : null;

        if (!$inamap || !$inamap->isPublished()) {
            return self::error('INAMAP_NOT_PUBLISHED', 404);
        }

        $token = $request->header('X-Inamap-Token');
        $session = $token ? $this->sessions->findByToken($token) : null;

        if ($token && (!$session || $session->inamap_id !== $inamap->id)) {
            return self::error('TOKEN_INVALID', 401);
        }

        if ($session && !$session->isUsable()) {
            return self::error('TOKEN_EXPIRED', 401);
        }

        // Credencial de dispositivo (kioscos): si se envía debe ser válida, y una
        // válida autoriza las lecturas que el kiosco necesita antes de crear una
        // sesión (dibujar el mapa, buscar, calcular la ruta).
        $accessKey = $request->header('X-Inamap-Access-Key');
        $deviceAuthorized = $accessKey && $inamap->accessKeyHash()
            && hash_equals($inamap->accessKeyHash(), hash('sha256', $accessKey));

        if ($accessKey && !$deviceAuthorized) {
            return self::error('ACCESS_KEY_INVALID', 403);
        }

        if (!$session && !$deviceAuthorized && $inamap->requiresToken() && !$this->isTokenExempt($request, $inamap)) {
            return self::error('TOKEN_REQUIRED', 401);
        }

        $request->attributes->set('inamap', $inamap);
        $request->attributes->set('route_session', $session);
        $request->attributes->set('device_authorized', (bool) $deviceAuthorized);

        return $next($request);
    }

    /**
     * Crear una sesión no exige token (es lo que lo genera); la autorización
     * de ese paso la hace RouteSessionController con la access_key.
     */
    private function isTokenExempt(Request $request, Inamap $inamap): bool
    {
        return $request->routeIs('api.inamap.public.routeSessions.create');
    }

    public static function error(string $code, int $status)
    {
        return response()->json(['error' => ['code' => $code, 'message' => __("inamap.errors.{$code}")]], $status);
    }
}
