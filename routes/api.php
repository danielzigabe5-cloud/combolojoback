<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\PayoutController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Dashboard\AdminDashboardController;
use App\Http\Controllers\Api\Dashboard\UserDashboardController;
use App\Http\Controllers\VenueController;
use App\Http\Controllers\ChapaController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\Resources\GameController;
use App\Http\Controllers\Api\Admin\AdminApprovalController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\NotificationSettingController;
use App\Http\Controllers\Api\Admin\ReportController;
use App\Http\Controllers\Api\Admin\AdminProfileController;
use App\Http\Controllers\Api\Admin\SettingsController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::get('/', fn () => response()->json([
    'success' => true,
    'message' => 'Backend is working correctly! Status 200 OK',
]));

// ============================================
// AUTH ROUTES
// ============================================
Route::prefix('auth')->group(function () {
    Route::post('/login',       [AuthController::class, 'login']);
    Route::post('/register',    [AuthController::class, 'register']);
    Route::post('/send-otp',    [AuthController::class, 'sendOTP']);
    Route::post('/verify-otp',  [AuthController::class, 'verifyOTP']);
    Route::post('/google',      [AuthController::class, 'googleLogin']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/complete-profile', [AuthController::class, 'completeProfile']);
        Route::get('/me',                [AuthController::class, 'me']);
        Route::post('/logout',           [AuthController::class, 'logout']);
    });
});

// ============================================
// PUBLIC ROUTES
// ============================================
Route::get('/venues',       [VenueController::class, 'index']);
Route::get('/venues/{id}',  [VenueController::class, 'show']);

// Bookings — public helpers
Route::get('/check-availability',    [BookingController::class, 'checkAvailability']);
Route::get('/available-time-slots',  [BookingController::class, 'getAvailableTimeSlots']);

// ============================================
// PROTECTED ROUTES
// ============================================
Route::middleware(['auth:sanctum'])->group(function () {

    // ── Venues (owner) ──
    Route::post('/venues', [VenueController::class, 'store']);
    Route::get('/my-venues', [VenueController::class, 'myVenues']);
    Route::put('/my-venues/{id}',    [VenueController::class, 'update']);
    Route::delete('/my-venues/{id}', [VenueController::class, 'destroy']);
    Route::get('/my-venues/{id}/schedule',  [VenueController::class, 'getSchedule']);
    Route::post('/my-venues/{id}/schedule', [VenueController::class, 'saveSchedule']);

    // ── Bookings ──
    Route::post('/bookings',      [BookingController::class, 'store']);
    Route::get('/my-bookings',    [BookingController::class, 'myBookings']);
    Route::get('/bookings/{id}',  [BookingController::class, 'show']);

    // ── Notification settings ──
    Route::get('/notification-settings',        [NotificationSettingController::class, 'show']);
    Route::post('/notification-settings',       [NotificationSettingController::class, 'update']);
    Route::post('/notification-settings/reset', [NotificationSettingController::class, 'reset']);
    Route::post('/notification-settings/test',  [NotificationSettingController::class, 'test']);

    // ============================================
    // ADMIN
    // ============================================
    Route::middleware('admin')->prefix('admin')->group(function () {

        // Dashboard
        Route::get('/dashboard', [AdminDashboardController::class, 'index']);

        // Users
        Route::get('/users',               [UserController::class, 'index']);
        Route::get('/users/{id}',          [UserController::class, 'show']);
        Route::patch('/users/{id}/role',   [UserController::class, 'updateRole']);
        Route::patch('/users/{id}/status', [UserController::class, 'updateStatus']);

        // Venues & Approvals
        Route::get('/venues',                  [AdminApprovalController::class, 'index']);
        Route::get('/approvals/pending',       [AdminApprovalController::class, 'pendingVenues']);
        Route::post('/approvals/{id}/approve', [AdminApprovalController::class, 'approveVenue']);
        Route::post('/approvals/{id}/reject',  [AdminApprovalController::class, 'rejectVenue']);

        // Bookings
        Route::get('/bookings-list',            [BookingController::class, 'adminIndex']);
        Route::post('/bookings/{id}/confirm',   [BookingController::class, 'confirm']);
        Route::post('/bookings/{id}/reject',    [BookingController::class, 'reject']);
    
     Route::get('/wallet',                 [PayoutController::class, 'wallet']);
    Route::get('/payouts',                [PayoutController::class, 'index']);
    Route::patch('/payouts/{id}/approve', [PayoutController::class, 'approve']);
    Route::patch('/payouts/{id}/reject',  [PayoutController::class, 'reject']);
       
     // 🆕 Settings
    Route::get('/settings',              [SettingsController::class, 'index']);
    Route::post('/settings',             [SettingsController::class, 'update']);
    Route::post('/settings/clear-cache', [SettingsController::class, 'clearCache']);
    Route::get('/settings/backup',       [SettingsController::class, 'backup']);

    Route::post('/profile', [AdminProfileController::class, 'update']);
    Route::get('/reports', [ReportController::class, 'index']);
    });

    // ── USER ──
    Route::middleware('user')->prefix('user')->group(function () {
        Route::get('/dashboard', [UserDashboardController::class, 'index']);
    });
});

// ============================================
// Chapa Payment
// ============================================
Route::prefix('chapa')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/initialize',    [ChapaController::class, 'initialize']);
        Route::get('/verify/{txRef}', [ChapaController::class, 'verify']);
    });
    Route::post('/callback', [ChapaController::class, 'callback']);
    Route::get('/return',    [ChapaController::class, 'return']);
});