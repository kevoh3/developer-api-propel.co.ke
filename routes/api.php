<?php

use App\Http\Controllers\AccessTokenController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\SendMoneyController;
use App\Http\Middleware\AuthenticateToken;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
Route::prefix('prod/v1')->group(function () {
    Route::get('/auth/token', [AccessTokenController::class, 'getToken']);
    Route::post('/auth/refresh-token', [AccessTokenController::class, 'refreshToken']);
    Route::middleware([AuthenticateToken::class])->group(function () {
        Route::get('/accounts/balance', [AccountController::class, 'getBalance']);
        Route::get('/accounts/transactions', [AccountController::class, 'getTransactions']);
        Route::post('/send-money/send-to-mobile', [SendMoneyController::class, 'storeSendToMobileMoneyRequest']);
        Route::post('/send-money/send-to-bank', [SendMoneyController::class, 'storeSendToBankRequest']);
        Route::post('/payment/b2b', [SendMoneyController::class, 'storePaymentRequest']);
        Route::post('/collection/initiate', [SendMoneyController::class, 'storeCollectionRequestFromMobile']);
    });
});

require base_path('routes/sandbox.php');
Route::fallback(function (Request $request) {
    return response()->json([
        'status' => false,
        'message' => 'Endpoint not found',
        'detail' => 'Resource not found',
        'ResponseCode'=>'404'
    ], 404);
});
