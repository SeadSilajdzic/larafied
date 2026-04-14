<?php

declare(strict_types=1);

use Larafied\Http\Controllers\CollectionController;
use Larafied\Http\Controllers\DashboardController;
use Larafied\Http\Controllers\NetworkController;
use Larafied\Http\Controllers\SyncController;
use Larafied\Http\Controllers\WsConfigController;
use Larafied\Http\Controllers\UnlockController;
use Larafied\Http\Controllers\HistoryController;
use Larafied\Http\Controllers\EnvironmentController;
use Larafied\Http\Controllers\FolderController;
use Larafied\Http\Controllers\LicenseController;
use Larafied\Http\Controllers\NoteController;
use Larafied\Http\Controllers\ProxyController;
use Larafied\Http\Controllers\RouteController;
use Larafied\Http\Controllers\SavedRequestController;
use Larafied\Http\Controllers\SqlController;
use Illuminate\Support\Facades\Route;

// Internal JSON API — consumed by the Vue SPA
Route::prefix('api')->name('larafied.api.')->group(function () {

    Route::get('routes', [RouteController::class, 'index'])
        ->name('routes');

    Route::post('proxy', [ProxyController::class, 'send'])
        ->name('proxy')
        ->middleware('throttle:60,1');

    Route::delete('collections', [CollectionController::class, 'bulkDestroy'])
        ->name('collections.bulk-destroy');

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

    Route::post('collections/{collection}/folders', [FolderController::class, 'store'])
        ->name('folders.store');
    Route::put('collections/{collection}/folders/{folder}', [FolderController::class, 'update'])
        ->name('folders.update');
    Route::delete('collections/{collection}/folders/{folder}', [FolderController::class, 'destroy'])
        ->name('folders.destroy');
    Route::post('collections/{collection}/folders/reorder', [FolderController::class, 'reorder'])
        ->name('folders.reorder');

    Route::delete('requests/{request}', [SavedRequestController::class, 'destroyRequest'])
        ->name('requests.destroy');
    Route::post('requests/{request}/duplicate', [SavedRequestController::class, 'duplicate'])
        ->name('requests.duplicate');
    Route::put('requests/{request}/move', [SavedRequestController::class, 'move'])
        ->name('requests.move');
    Route::post('requests/reorder', [SavedRequestController::class, 'reorder'])
        ->name('requests.reorder');

    Route::get('notes', [NoteController::class, 'index'])->name('notes.index');
    Route::get('notes/find', [NoteController::class, 'show'])->name('notes.show');
    Route::put('notes', [NoteController::class, 'upsert'])->name('notes.upsert');
    Route::delete('notes', [NoteController::class, 'destroy'])->name('notes.destroy');

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

    Route::get('license', [LicenseController::class, 'show'])
        ->name('license.show');

    Route::post('license/activate', [LicenseController::class, 'activate'])
        ->name('license.activate');

    Route::post('sql', [SqlController::class, 'execute'])
        ->name('sql.execute');

    Route::get('history', [HistoryController::class, 'index'])->name('history.index');
    Route::delete('history', [HistoryController::class, 'destroy'])->name('history.destroy');

    Route::get('sync/status', [SyncController::class, 'status'])->name('sync.status');
    Route::post('sync/push', [SyncController::class, 'push'])->name('sync.push');
    Route::post('sync/pull', [SyncController::class, 'pull'])->name('sync.pull');

    Route::get('network/config', [NetworkController::class, 'config'])->name('network.config');
    Route::get('network/events', [NetworkController::class, 'index'])->name('network.events');
    Route::delete('network/events', [NetworkController::class, 'clear'])->name('network.clear');

    Route::get('ws/config', [WsConfigController::class, 'config'])->name('ws.config');

});

// Password unlock — must be before the SPA catch-all
Route::get('unlock', [UnlockController::class, 'show'])->name('larafied.unlock');
Route::post('unlock', [UnlockController::class, 'store'])->name('larafied.unlock.store');

// SPA entry point — must be last to avoid catching API routes
Route::get('/{any?}', [DashboardController::class, 'index'])
    ->where('any', '^(?!api/).*$')
    ->name('larafied.dashboard');
