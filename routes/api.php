<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\ClientDashboardController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\ReportsController;
use App\Http\Controllers\Api\RewardController;
use App\Http\Controllers\Api\SalonClientController;
use App\Http\Controllers\Api\SalonFavoriteController;
use App\Http\Controllers\Api\SalonController;
use App\Http\Controllers\Api\SalonDashboardController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Auth\OtpAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth/otp')->group(function () {
    Route::post('/request', [OtpAuthController::class, 'request'])
        ->middleware('throttle:5,1');
    Route::post('/verify', [OtpAuthController::class, 'verify'])
        ->middleware('throttle:10,1');
});

Route::post('/webhooks/salon-signup', [WebhookController::class, 'salonSignup'])
    ->middleware('throttle:30,1');

// Triggers a real-salon OpenStreetMap import — production has no shell
// access to run `salons:import-osm` directly. Shared-secret protected,
// same pattern as the webhook above.
Route::post('/internal/salons/import-osm', \App\Http\Controllers\Api\SalonImportController::class)
    ->middleware('throttle:20,1');

// Public marketing-site signup form — no shared secret, since a public form
// can be called by anyone. Only ever captures a lead, never a real account.
Route::post('/leads', [LeadController::class, 'store'])
    ->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', function (\Illuminate\Http\Request $request) {
        return $request->user()->load(['client', 'salon.subscription']);
    });

    Route::post('/auth/logout', [OtpAuthController::class, 'logout']);
    Route::patch('/me', [AccountController::class, 'update']);
    Route::get('/me/export', [AccountController::class, 'export']);
    Route::delete('/me', [AccountController::class, 'destroy']);
    Route::get('/salons', [SalonController::class, 'index']);
    Route::post('/salons', [SalonController::class, 'store']);
    Route::patch('/salons', [SalonController::class, 'update']);
    Route::get('/salons/dashboard', [SalonDashboardController::class, 'show']);
    Route::get('/salons/clients', [SalonClientController::class, 'index']);
    Route::get('/salons/favorites', [SalonFavoriteController::class, 'index']);
    Route::post('/salons/{salon}/favorite', [SalonFavoriteController::class, 'toggle']);
    Route::post('/salons/subscription', [SubscriptionController::class, 'store']);
    Route::delete('/salons/subscription', [SubscriptionController::class, 'destroy']);

    Route::get('/rewards', [RewardController::class, 'index']);
    Route::post('/rewards', [RewardController::class, 'store']);
    Route::patch('/rewards/{reward}', [RewardController::class, 'update']);

    Route::post('/referrals', [ReferralController::class, 'store']);
    Route::patch('/referrals/{referral}/engage', [ReferralController::class, 'engage']);
    Route::patch('/referrals/{referral}/complete', [ReferralController::class, 'complete']);
    Route::get('/clients/dashboard', [ClientDashboardController::class, 'show']);

    Route::get('/content', [ContentController::class, 'index']);
    Route::post('/content', [ContentController::class, 'store']);
    Route::delete('/content/{post}', [ContentController::class, 'destroy']);
    Route::get('/content/{post}/image', [ContentController::class, 'showImage'])->name('content.image');
    Route::post('/content/{post}/like', [ContentController::class, 'toggleLike']);
    Route::get('/salons/{salon}/content', [ContentController::class, 'forSalon']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead']);

    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/stats', [AdminController::class, 'stats']);
        Route::get('/subscriptions', [AdminController::class, 'subscriptions']);
        Route::get('/leads', [AdminController::class, 'leads']);
        Route::get('/reports', [ReportsController::class, 'show']);
        Route::get('/reports/export.csv', [ReportsController::class, 'exportCsv']);
        Route::get('/reports/export.pdf', [ReportsController::class, 'exportPdf']);
    });
});
