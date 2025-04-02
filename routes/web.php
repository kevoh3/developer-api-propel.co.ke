<?php

use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;
//
//Route::get('/', function () {
//    return view('welcome');
//});
Route::fallback(function (Request $request) {
    return response()->json([
        'status' => false,
        'message' => 'Endpoint not found',
        'detail' => 'Resource not found',
        'ResponseCode'=>'404'
    ], 404);
});
