<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConnectedAccountController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\PositionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Auth — login/register are public (throttled); logout/me require a valid token.
    Route::post('/auth/register', [AuthController::class, 'register'])
        ->middleware('throttle:login');
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:login');
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me',     [AuthController::class, 'me']);
    });

    // Public — stateless position analysis.
    Route::post('/positions/analyse', [PositionController::class, 'analyse'])
        ->middleware('throttle:position-analysis');

    // Public — share-code lookup (intentionally unauthenticated).
    Route::get('/games/by-share-code/{code}', [GameController::class, 'showByShareCode']);

    // Public — profile reads by chess.com username.
    Route::get('/connected-accounts/by-username/{platform}/{username}/games', [ConnectedAccountController::class, 'gamesByUsername']);
    Route::get('/connected-accounts/by-username/{platform}/{username}/stats', [ConnectedAccountController::class, 'statsByUsername']);
    Route::get('/connected-accounts/by-username/{platform}/{username}', [ConnectedAccountController::class, 'showByUsername']);

    // Private — all game and account management routes require auth.
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/games', [GameController::class, 'index']);
        Route::post('/games', [GameController::class, 'store']);
        Route::get('/games/{id}', [GameController::class, 'show']);
        Route::post('/games/{id}/analyse', [GameController::class, 'analyse']);

        Route::get('/connected-accounts', [ConnectedAccountController::class, 'index']);
        Route::post('/connected-accounts', [ConnectedAccountController::class, 'store']);
        Route::delete('/connected-accounts/{id}', [ConnectedAccountController::class, 'destroy']);
        Route::post('/connected-accounts/by-username/{platform}/{username}/sync', [ConnectedAccountController::class, 'sync']);
    });
});
