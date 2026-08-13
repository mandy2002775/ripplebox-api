<?php

use App\Http\Controllers\Api\SalonController;
use App\Http\Controllers\Auth\OtpAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth/otp')->group(function () {
    Route::post('/request', [OtpAuthController::class, 'request'])
        ->middleware('throttle:5,1');
    Route::post('/verify', [OtpAuthController::class, 'verify'])
        ->middleware('throttle:10,1');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', function (\Illuminate\Http\Request $request) {
        return $request->user()->load(['client', 'salon']);
    });

    Route::post('/auth/logout', [OtpAuthController::class, 'logout']);
    Route::post('/salons', [SalonController::class, 'store']);
});
