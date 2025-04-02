<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AccessTokenController;

Route::prefix('sandbox/v1')->group(function () {
    Route::get('/auth/token', [AccessTokenController::class, 'getToken']);
    Route::post('/auth/refresh-token', [AccessTokenController::class, 'refreshToken']);
});
