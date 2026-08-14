<?php

use App\Http\Controllers\Api\ClientDashboardController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\RewardController;
use App\Http\Controllers\Api\SalonController;
use App\Http\Controllers\Api\SalonDashboardController;
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
    Route::get('/salons', [SalonController::class, 'index']);
    Route::post('/salons', [SalonController::class, 'store']);
    Route::get('/salons/dashboard', [SalonDashboardController::class, 'show']);

    Route::get('/rewards', [RewardController::class, 'index']);
    Route::post('/rewards', [RewardController::class, 'store']);
    Route::patch('/rewards/{reward}', [RewardController::class, 'update']);

    Route::post('/referrals', [ReferralController::class, 'store']);
    Route::patch('/referrals/{referral}/complete', [ReferralController::class, 'complete']);
    Route::get('/clients/dashboard', [ClientDashboardController::class, 'show']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead']);
});
