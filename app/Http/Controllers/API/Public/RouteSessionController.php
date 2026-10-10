<?php

namespace Domain\App\Http\Controllers\API\Public;

use App\Http\Controllers\Controller;
use Domain\App\Http\Middleware\InamapAccessToken;
use Domain\App\Models\Inamap\Inamap;
use Domain\App\Models\Inamap\RouteSession;
use Domain\App\Services\Inamap\Qr\QrService;
use Domain\App\Services\Inamap\Routing\RouteException;
use Domain\App\Services\Inamap\Session\RouteSessionService;
use Illuminate\Http\Request;

/**
 * Sesiones de ruta y QR (S4, README §3.6 y FL-09).
 */
class RouteSessionController extends Controller
{
    public function __construct(private readonly RouteSessionService $sessions)
    {
    }

    /** POST api/inamap/route-sessions {inamap, from, to, accessible?} */
    public function create(Request $request)
    {
        $request->validate([
            'from'       => 'required|string|max:80',
            'to'         => 'required|string|max:80',
            'accessible' => 'sometimes|boolean',
        ]);

        /** @var Inamap $inamap */
        $inamap = $request->attributes->get('inamap');
        $user = auth('sanctum')->user();
        $isEditor = $user && $inamap->canBeManagedBy($user);
        $hasDeviceKey = $this->validAccessKey($inamap, $request->header('X-Inamap-Access-Key'));

        if ($request->header('X-Inamap-Access-Key') && !$hasDeviceKey) {
            return InamapAccessToken::error('ACCESS_KEY_INVALID', 403);
        }

        // En token_required solo un dispositivo con access_key, un editor
        // del tenant o alguien que ya tiene una sesión válida crean sesiones.
        if ($inamap->requiresToken() && !$hasDeviceKey && !$isEditor && !$request->attributes->get('route_session')) {
            return InamapAccessToken::error('ACCESS_KEY_INVALID', 403);
        }

        try {
            $created = $this->sessions->create(
                $inamap,
                $request->input('from'),
                $request->input('to'),
                $request->boolean('accessible'),
                $isEditor ? 'editor' : ($hasDeviceKey ? 'device' : 'visitor'),
                $isEditor ? $user : null,
            );
        } catch (RouteException $e) {
            return InamapAccessToken::error($e->errorCode, $e->httpStatus());
        }

        return response()->json(['data' => [
            'session_id' => $created['session']->id,
            'token'      => $created['token'],
            'view_url'   => $created['view_url'],
            'qr_svg'     => $created['qr_svg'],
            'expires_at' => $created['session']->expires_at,
            'route'      => $created['session']->route,
        ]], 201);
    }

    /** GET api/inamap/route-sessions/current — con X-Inamap-Token. */
    public function current(Request $request)
    {
        $session = $this->sessions->findByToken($request->header('X-Inamap-Token'));

        if (!$session) {
            return InamapAccessToken::error('TOKEN_INVALID', 401);
        }
        if (!$session->isUsable()) {
            return InamapAccessToken::error('TOKEN_EXPIRED', 401);
        }
        if (!$session->inamap?->isPublished()) {
            return InamapAccessToken::error('INAMAP_NOT_PUBLISHED', 404);
        }

        $this->sessions->markOpened($session);

        return response()->json(['data' => [
            'session_id' => $session->id,
            'inamap_id'  => $session->inamap_id,
            'expires_at' => $session->expires_at,
            'route'      => $session->route,
        ]])->header('Cache-Control', 'private, no-store');
    }

    /** GET api/inamap/route-sessions/{sessionId}/qr?format= — con X-Inamap-Token de esa sesión. */
    public function qr(Request $request, string $sessionId)
    {
        $token = (string) $request->header('X-Inamap-Token');
        $session = $this->sessions->findByToken($token);

        if (!$session || $session->id !== $sessionId) {
            return InamapAccessToken::error('TOKEN_INVALID', 401);
        }
        if (!$session->isUsable()) {
            return InamapAccessToken::error('TOKEN_EXPIRED', 401);
        }

        $format = in_array($request->query('format'), QrService::FORMATS, true) ? $request->query('format') : 'svg';
        $qr = app(QrService::class)->render($this->sessions->viewUrl($session->inamap, $token), $format);

        return response($qr['content'], 200, ['Content-Type' => $qr['mime'], 'Cache-Control' => 'private, no-store']);
    }

    private function validAccessKey(Inamap $inamap, ?string $key): bool
    {
        $hash = $inamap->accessKeyHash();

        return $key && $hash && hash_equals($hash, hash('sha256', $key));
    }
}
