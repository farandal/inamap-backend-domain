<?php

use Domain\App\Http\Controllers\API\Public\PublicInamapController;
use Domain\App\Http\Controllers\API\Public\RouteSessionController;
use Domain\App\Http\Middleware\InamapAccessToken;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API pública del visor INAMAP (README §9.2)
|--------------------------------------------------------------------------
|
| Sin autenticación ni `access`: no hay usuario que tenga un permiso. El
| acceso lo decide InamapAccessToken según el access_mode de cada inamap.
| Estas rutas no van en permissions.json.
|
*/

Route::prefix('inamap')->name('inamap.public.')->group(function () {

    Route::middleware(['throttle:inamap-public', InamapAccessToken::class])->group(function () {
        Route::get('search', [PublicInamapController::class, 'search'])->name('search');
        Route::get('route', [PublicInamapController::class, 'route'])->name('route');
        Route::get('{id}', [PublicInamapController::class, 'show'])->whereUuid('id')->name('show');
        Route::get('{id}/render', [PublicInamapController::class, 'render'])->whereUuid('id')->name('render');
        Route::get('{id}/locations/{locationId}', [PublicInamapController::class, 'location'])
            ->whereUuid('id')->whereUuid('locationId')->name('location');
    });

    Route::prefix('route-sessions')->name('routeSessions.')->middleware('throttle:inamap-sessions')->group(function () {
        Route::post('/', [RouteSessionController::class, 'create'])
            ->middleware(InamapAccessToken::class)
            ->name('create');
        Route::get('current', [RouteSessionController::class, 'current'])->name('current');
        Route::get('{sessionId}/qr', [RouteSessionController::class, 'qr'])->whereUuid('sessionId')->name('qr');
    });
});
