<?php

namespace Domain\App\Http\Controllers\API\Inamap\Inamap;

use App\Support\ResponseHandler;
use Domain\App\Http\Controllers\API\Inamap\InamapBaseController;
use Domain\App\Models\Inamap\Inamap;
use Domain\App\Services\Inamap\Diagnostics\InamapConnectivityService;
use Domain\App\Services\Inamap\Plan\InamapPlanLimits;
use Domain\App\Services\Inamap\Routing\RouteException;
use Domain\App\Services\Inamap\Routing\RouteService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InamapController extends InamapBaseController
{
    public $resource         = InamapResource::class;
    public $requestValidator = InamapRequest::class;
    public $modelFilter      = InamapFilter::class;
    public $_model           = Inamap::class;

    protected array $with = ['layouts'];

    public function _create($request)
    {
        $this->_authorize('create');
        $validated = app($this->requestValidator)->validated();
        $owner = $this->tenantContext()->forWrite($request->user(), $validated['tenant_id'] ?? null);

        app(InamapPlanLimits::class)->enforce($owner['tenancy_id'], 'max_inamaps', $request->user());

        $item = Inamap::create(array_merge($validated, $owner, [
            'slug'     => $this->uniqueSlug($owner['tenant_id'], $validated['slug'] ?? $validated['name']),
            'unit'     => $validated['unit'] ?? config('inamap.default_unit', 'm'),
            'status'   => Inamap::STATUS_DRAFT,
            'settings' => $this->sanitizeSettings($validated['settings'] ?? [], []),
        ]));

        return $this->afterSave($request, $item->fresh($this->with));
    }

    public function _update($request, $id, $item)
    {
        $item = $this->found($item);
        $this->_authorize('manage', $item);

        $validated = app($this->requestValidator)->validated();
        unset($validated['tenant_id'], $validated['tenancy_id'], $validated['status'], $validated['published_at']);

        // La unidad solo puede cambiar mientras no haya geometría que reinterpretar.
        if (isset($validated['unit']) && $validated['unit'] !== $item->unit && $item->layouts()->exists()) {
            unset($validated['unit']);
        }

        if (isset($validated['slug'])) {
            $validated['slug'] = $this->uniqueSlug($item->tenant_id, $validated['slug'], $item->id);
        }

        if (array_key_exists('settings', $validated)) {
            // La omisión nunca borra: se fusiona sobre lo existente.
            $validated['settings'] = $this->sanitizeSettings($validated['settings'] ?? [], $item->settings ?? []);
        }

        $item->update($validated);

        return $this->afterSave($request, $item->fresh($this->with));
    }

    public function publish(Request $request, string $id)
    {
        return $this->setStatus($request, $id, Inamap::STATUS_PUBLISHED);
    }

    public function unpublish(Request $request, string $id)
    {
        return $this->setStatus($request, $id, Inamap::STATUS_DRAFT);
    }

    /** Genera una nueva credencial de dispositivo; se devuelve una sola vez. */
    public function rotateAccessKey(Request $request, string $id)
    {
        $item = $this->ownedInamap($request, $id);
        $this->_authorize('manage', $item);

        $key = 'imk_' . Str::random(40);
        $item->update(['settings' => array_merge($item->settings ?? [], ['access_key_hash' => hash('sha256', $key)])]);

        return ResponseHandler::json(['data' => ['access_key' => $key, 'inamap_id' => $item->id]]);
    }

    public function connectivity(Request $request, string $id)
    {
        $item = $this->ownedInamap($request, $id);

        return ResponseHandler::json([
            'data' => app(InamapConnectivityService::class)->diagnose($item, $request->boolean('accessible')),
        ]);
    }

    /** Ruta de prueba autenticada: funciona también sobre borradores. */
    public function route(Request $request, string $id)
    {
        $request->validate(['from' => 'required|string|max:80', 'to' => 'required|string|max:80']);
        $item = $this->ownedInamap($request, $id);

        try {
            return ResponseHandler::json([
                'data' => app(RouteService::class)->route($item, $request->input('from'), $request->input('to'), $request->boolean('accessible')),
            ]);
        } catch (RouteException $e) {
            return ResponseHandler::json(['error' => ['code' => $e->errorCode, 'message' => __("inamap.errors.{$e->errorCode}")]], $e->httpStatus());
        }
    }

    private function setStatus(Request $request, string $id, string $status)
    {
        $item = $this->ownedInamap($request, $id);
        $this->_authorize('manage', $item);

        $item->update([
            'status'       => $status,
            'published_at' => $status === Inamap::STATUS_PUBLISHED ? ($item->published_at ?? now()) : $item->published_at,
        ]);

        return ResponseHandler::json(['data' => InamapResource::make($item->fresh('layouts'))]);
    }

    private function sanitizeSettings(array $incoming, array $current): array
    {
        unset($incoming['access_key_hash'], $incoming['has_access_key']);

        return array_merge($current, $incoming);
    }

    private function uniqueSlug(string $tenantId, string $source, ?string $ignoreId = null): string
    {
        $base = Str::slug($source) ?: 'inamap';
        $slug = $base;
        $n = 2;

        while (Inamap::query()
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = "{$base}-{$n}";
            $n++;
        }

        return $slug;
    }
}
