<?php

use App\Http\Controllers\AccessTokenController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\SendMoneyController;
use App\Http\Middleware\AuthenticateToken;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::get('/v1/auth/token', [AccessTokenController::class, 'getToken']);
Route::post('/v1/auth/refresh-token', [AccessTokenController::class, 'refreshToken']);
Route::middleware([AuthenticateToken::class])->group(function () {
    Route::get('/v1/accounts/balance', [AccountController::class, 'getBalance']);
    Route::get('/v1/accounts/transactions', [AccountController::class, 'getTransactions']);
    Route::post('/v1/send-money/send-to-mobile', [SendMoneyController::class, 'storeSendToMobileMoneyRequest']);
    Route::post('/v1/send-money/send-to-bank', [SendMoneyController::class, 'storeSendToBankRequest']);
    Route::post('/v1/payment/b2b', [SendMoneyController::class, 'storePaymentRequest']);
    Route::post('/v1/collection/initiate', [SendMoneyController::class, 'storeCollectionRequestFromMobile']);
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
