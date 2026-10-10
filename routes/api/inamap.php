<?php

use Domain\App\Http\Controllers\API\Inamap\Category\CategoryController;
use Domain\App\Http\Controllers\API\Inamap\Editor\LayoutGeometryController;
use Domain\App\Http\Controllers\API\Inamap\Inamap\InamapController;
use Domain\App\Http\Controllers\API\Inamap\Layout\LayoutController;
use Domain\App\Http\Controllers\API\Inamap\Location\LocationController;
use Domain\App\Http\Controllers\API\Inamap\Path\PathController;
use Domain\App\Http\Controllers\API\Inamap\PathLink\PathLinkController;
use Domain\App\Http\Controllers\API\Inamap\Structure\StructureController;
use Domain\App\Http\Controllers\API\Inamap\Zone\ZoneController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API autenticada de INAMAP (README §9.1)
|--------------------------------------------------------------------------
|
| La entidad raíz se expone como `inamaps`; todo lo demás cuelga de
| `inamap/`. Nombres de ruta (= permisos): api.inamaps.* y
| api.inamap.<entidad>.*. El middleware `access` del core comprueba el
| permiso por route_name (database/data/permissions.json).
|
*/

$registerCrud = function (string $class, array $except = []) {
    foreach (config('react-admin-methods') as $methodName => $method) {
        if (in_array($methodName, $except, true)) {
            continue;
        }

        Route::{$method['method']}($method['path'], [$class, $method['controllerMethod']])
            ->middleware('ControllerOptions:mode/' . $method['mode'])
            ->name($methodName);
    }
};

Route::group(['middleware' => ['access', 'auth:sanctum']], function () use ($registerCrud) {

    Route::prefix('inamaps')->name('inamaps.')->group(function () use ($registerCrud) {
        Route::post('{id}/publish', [InamapController::class, 'publish'])->whereUuid('id')->name('publish');
        Route::post('{id}/unpublish', [InamapController::class, 'unpublish'])->whereUuid('id')->name('unpublish');
        Route::post('{id}/access-key/rotate', [InamapController::class, 'rotateAccessKey'])->whereUuid('id')->name('accessKey.rotate');
        Route::get('{id}/connectivity', [InamapController::class, 'connectivity'])->whereUuid('id')->name('connectivity');
        Route::get('{id}/route', [InamapController::class, 'route'])->whereUuid('id')->name('route');

        $registerCrud(InamapController::class);
    });

    Route::prefix('inamap')->name('inamap.')->group(function () use ($registerCrud) {
        Route::prefix('layouts')->name('layout.')->group(function () use ($registerCrud) {
            Route::get('{id}/geometry', [LayoutGeometryController::class, 'show'])->whereUuid('id')->name('geometry.show');
            Route::put('{id}/geometry', [LayoutGeometryController::class, 'update'])->whereUuid('id')->name('geometry');
            Route::post('{id}/image', [LayoutController::class, 'image'])->whereUuid('id')->name('image');

            $registerCrud(LayoutController::class);
        });

        Route::prefix('categories')->name('category.')->group(function () use ($registerCrud) {
            Route::post('updateTree', [CategoryController::class, 'updateTree'])->name('updateTree');

            $registerCrud(CategoryController::class);
        });

        Route::prefix('locations')->name('location.')->group(function () use ($registerCrud) {
            Route::get('{id}/qr', [LocationController::class, 'qr'])->whereUuid('id')->name('qr');

            $registerCrud(LocationController::class);
        });

        foreach ([
            'zones'      => ['zone', ZoneController::class],
            'structures' => ['structure', StructureController::class],
            'paths'      => ['path', PathController::class],
            'path-links' => ['pathLink', PathLinkController::class],
        ] as $prefix => [$name, $class]) {
            Route::prefix($prefix)->name("{$name}.")->group(fn () => $registerCrud($class));
        }
    });
});
