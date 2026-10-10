<?php

use Illuminate\Support\Facades\Route;

/*
| GET /inamap/{id} — enlace público estable del visor (RF-22). Redirige a
| inamap-app conservando query y fragmento del cliente.
*/
Route::get('/inamap/{id}', function (string $id) {
    $query = request()->getQueryString();

    return redirect()->away(
        rtrim((string) config('inamap.route_sessions.app_url'), '/') . "/inamap/{$id}" . ($query ? "?{$query}" : '')
    );
})->whereUuid('id')->name('inamap.viewer');
