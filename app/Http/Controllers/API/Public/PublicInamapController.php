<?php

namespace Domain\App\Http\Controllers\API\Public;

use App\Http\Controllers\Controller;
use Domain\App\Http\Controllers\API\Inamap\Location\LocationResource;
use Domain\App\Http\Controllers\API\Inamap\PathLink\PathLinkResource;
use Domain\App\Http\Middleware\InamapAccessToken;
use Domain\App\Models\Inamap\Category;
use Domain\App\Models\Inamap\Inamap;
use Domain\App\Models\Inamap\Location;
use Domain\App\Models\Inamap\PathLink;
use Domain\App\Services\Inamap\Routing\RouteException;
use Domain\App\Services\Inamap\Routing\RouteService;
use Domain\App\Services\Inamap\Search\LocationSearchService;
use Illuminate\Http\Request;

/**
 * API pública del visor (README §9.2). Sin autenticación: el acceso lo decide
 * el middleware InamapAccessToken según el `access_mode` del inamap.
 */
class PublicInamapController extends Controller
{
    /** GET api/inamap/{id} — todo lo necesario para dibujar el inamap. */
    public function show(Request $request, string $id)
    {
        /** @var Inamap $inamap */
        $inamap = $request->attributes->get('inamap');
        $inamap->load(['layouts', 'zones', 'structures', 'locations.category']);

        $payload = [
            'id'          => $inamap->id,
            'name'        => $inamap->name,
            'description' => $inamap->description,
            'unit'        => $inamap->unit,
            'settings'    => array_intersect_key($inamap->publicSettings(), array_flip([
                'access_mode', 'initial_layout_id', 'initial_zoom', 'primary_color',
            ])),
            'layouts'     => $inamap->layouts->map(fn ($l) => $l->only(['id', 'name', 'level', 'width', 'height', 'image_url']))->values(),
            'zones'       => $inamap->zones->map(fn ($z) => $z->only(['id', 'layout_id', 'code', 'label', 'color', 'polygon']))->values(),
            'structures'  => $inamap->structures->map(fn ($s) => $s->only(['id', 'layout_id', 'code', 'label', 'color', 'polygon']))->values(),
            'locations'   => LocationResource::collection($inamap->locations)->resolve($request),
            'categories'  => Category::query()
                ->whereIn('id', $inamap->locations->pluck('category_id')->filter()->unique())
                ->get(['id', 'category_id', 'name', 'color', 'icon', 'image_path'])
                ->map(fn ($c) => $c->only(['id', 'category_id', 'name', 'color', 'icon', 'image_url']))
                ->values(),
        ];

        if ($request->query('include') === 'paths') {
            $payload['paths'] = $inamap->paths()->get(['id', 'layout_id', 'points', 'direction'])->values();
            $payload['path_links'] = PathLinkResource::collection(PathLink::where('inamap_id', $inamap->id)->get())->resolve($request);
        }

        $response = response()->json(['data' => $payload]);

        if ($inamap->requiresToken()) {
            return $response->header('Cache-Control', 'private, no-store');
        }

        $response->setEtag(md5($response->getContent()))->setPublic()->setMaxAge(60);
        $response->isNotModified($request);

        return $response;
    }

    /** GET api/inamap/{id}/locations/{locationId} — ficha de una ubicación. */
    public function location(Request $request, string $id, string $locationId)
    {
        $location = Location::with('category')->where('inamap_id', $id)->find($locationId);

        if (!$location) {
            return InamapAccessToken::error('ENDPOINT_NOT_FOUND', 404);
        }

        return response()->json(['data' => LocationResource::make($location)->resolve($request)]);
    }

    /** GET api/inamap/search?inamap=&q=&category=&layout=&type=&limit= */
    public function search(Request $request)
    {
        $request->validate([
            'q'        => 'nullable|string|max:120',
            'category' => 'nullable|uuid',
            'layout'   => 'nullable|uuid',
            'type'     => 'nullable|in:zone,structure,location',
            'limit'    => 'nullable|integer|min:1|max:' . config('inamap.search.max_limit', 50),
        ]);

        $results = app(LocationSearchService::class)->search(
            $request->attributes->get('inamap'),
            (string) $request->query('q', ''),
            $request->only(['category', 'layout', 'type', 'limit']),
        );

        return response()->json(['data' => $results]);
    }

    /** GET api/inamap/route?inamap=&from=type:id&to=type:id&accessible= */
    public function route(Request $request)
    {
        $request->validate(['from' => 'required|string|max:80', 'to' => 'required|string|max:80']);

        try {
            return response()->json(['data' => app(RouteService::class)->route(
                $request->attributes->get('inamap'),
                $request->query('from'),
                $request->query('to'),
                $request->boolean('accessible'),
            )]);
        } catch (RouteException $e) {
            return InamapAccessToken::error($e->errorCode, $e->httpStatus());
        }
    }
}
