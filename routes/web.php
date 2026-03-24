<?php

declare(strict_types=1);

use Larafied\Http\Controllers\CollectionController;
use Larafied\Http\Controllers\DashboardController;
use Larafied\Http\Controllers\EnvironmentController;
use Larafied\Http\Controllers\ProxyController;
use Larafied\Http\Controllers\RouteController;
use Larafied\Http\Controllers\SavedRequestController;
use Illuminate\Support\Facades\Route;

// Internal JSON API — consumed by the Vue SPA
Route::prefix('api')->name('larafied.api.')->group(function () {

    Route::get('routes', [RouteController::class, 'index'])
        ->name('routes');

    Route::post('proxy', [ProxyController::class, 'send'])
        ->name('proxy')
        ->middleware('throttle:60,1');

    Route::apiResource('collections', CollectionController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->names([
            'index'   => 'collections.index',
            'store'   => 'collections.store',
            'update'  => 'collections.update',
            'destroy' => 'collections.destroy',
        ]);

    Route::apiResource('collections.requests', SavedRequestController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->names([
            'index'   => 'requests.index',
            'store'   => 'requests.store',
            'update'  => 'requests.update',
            'destroy' => 'requests.destroy',
        ]);

    Route::apiResource('environments', EnvironmentController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->names([
            'index'   => 'environments.index',
            'store'   => 'environments.store',
            'update'  => 'environments.update',
            'destroy' => 'environments.destroy',
        ]);

    Route::post('environments/{id}/activate', [EnvironmentController::class, 'activate'])
        ->name('environments.activate');

});

// SPA entry point — must be last to avoid catching API routes
Route::get('/{any?}', [DashboardController::class, 'index'])
    ->where('any', '^(?!api/).*$')
    ->name('larafied.dashboard');
