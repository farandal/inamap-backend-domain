<?php

use Illuminate\Support\Facades\Route;

Route::group(['as' => 'prerelease.', 'prefix' => 'prerelease'], function () {

    // Public landing-page capture endpoint (no auth, throttled)
    $publicClass = \Domain\App\Http\Controllers\API\Prerelease\PublicPrereleaseSignupController::class;
    Route::post('register', [$publicClass, 'register'])
        ->middleware('throttle:10,1')
        ->name('register');

    // System-admin CRUD resource (react-admin)
    Route::group(['middleware' => ['auth:sanctum']], function () {

        Route::prefix('signup')->name('signup.')->group(function () {
            $class = \Domain\App\Http\Controllers\API\Prerelease\Signup\PrereleaseSignupController::class;
            $RAMethods = config('react-admin-methods');

            foreach ($RAMethods as $methodName => $methodValues) {
                Route::{$methodValues['method']}($methodValues['path'], [$class, $methodValues['controllerMethod']])
                    ->middleware('ControllerOptions:mode/' . $methodValues['mode'])
                    ->name($methodName);
            }
        });

    });

});
