<?php

use App\Http\Controllers\ConnectedAccountController;
use App\Http\Controllers\GameController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/games', [GameController::class, 'index']);
    Route::post('/games', [GameController::class, 'store']);
    Route::get('/games/by-share-code/{code}', [GameController::class, 'showByShareCode']);
    Route::get('/games/{id}', [GameController::class, 'show']);

    Route::get('/connected-accounts', [ConnectedAccountController::class, 'index']);
    Route::post('/connected-accounts', [ConnectedAccountController::class, 'store']);
    Route::get('/connected-accounts/by-username/{platform}/{username}/games', [ConnectedAccountController::class, 'gamesByUsername']);
    Route::get('/connected-accounts/by-username/{platform}/{username}', [ConnectedAccountController::class, 'showByUsername']);
});
