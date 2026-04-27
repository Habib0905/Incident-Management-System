<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\IncidentController;
use App\Http\Controllers\Api\LogController;
use App\Http\Controllers\Api\ServerController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::prefix('me')->group(function () {
        Route::get('/incidents', [IncidentController::class, 'myIncidents']);
        Route::get('/unread-count', [IncidentController::class, 'unreadCount']);
    });

    Route::middleware('role:admin')->group(function () {
        Route::prefix('admin')->group(function () {
            Route::apiResource('servers', ServerController::class);
            Route::post('/servers/{server}/regenerate-key', [ServerController::class, 'regenerateKey']);
            Route::post('/servers/{server}/revoke-key', [ServerController::class, 'revokeKey']);
            Route::post('/servers/{server}/activate-key', [ServerController::class, 'activateKey']);
            Route::apiResource('users', UserController::class);
        });
    });

    Route::post('/incidents/{incident}/assign', [IncidentController::class, 'assign']);
    Route::post('/incidents/{incident}/notes', [IncidentController::class, 'addNote']);
    Route::post('/incidents/{incident}/generate-summary', [IncidentController::class, 'generateSummary']);
    Route::post('/incidents/{incident}/view', [IncidentController::class, 'view']);
    Route::get('/incidents/{incident}/timeline', [IncidentController::class, 'timeline']);
    Route::delete('/incidents/{incident}', [IncidentController::class, 'destroy']);

    Route::apiResource('incidents', IncidentController::class)->except(['store', 'destroy']);
});

Route::post('/logs', [LogController::class, 'store']);
Route::get('/logs', [LogController::class, 'index']);