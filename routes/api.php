<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\PayoutController as AdminPayoutController;
use App\Http\Controllers\Api\PayoutController;
use App\Http\Controllers\Api\EarningsController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Dashboard\AdminDashboardController;
use App\Http\Controllers\Api\Dashboard\UserDashboardController;
use App\Http\Controllers\Api\Partner\PartnerDashboardController;
use App\Http\Controllers\VenueController;
use App\Http\Controllers\ChapaController;
use App\Http\Controllers\Api\ProfileController;
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
        Route::get('/me',              [AuthController::class, 'me']);
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::match(['put', 'patch', 'post'], '/profile', [ProfileController::class, 'update']);
        Route::delete('/profile/avatar', [ProfileController::class, 'removeAvatar']);
        Route::post('/logout',           [AuthController::class, 'logout']);
    });
});

// ============================================
// PUBLIC ROUTES
// ============================================
Route::get('/venues',       [VenueController::class, 'index']);
Route::get('/venues/{id}',  [VenueController::class, 'show']);

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
    Route::post('/my-venues/{id}/schedule/toggle-block', [VenueController::class, 'toggleBlock']);

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
    // 🆕 PARTNER DASHBOARD (የተለየ ቡድን - ራሱን የቻለ)
    // ============================================
    Route::middleware('partner')->prefix('partner')->group(function () {
        // Dashboard
        Route::get('/dashboard', [PartnerDashboardController::class, 'index'])
            ->name('partner.dashboard');

        // Bookings
        Route::get('/bookings',               [BookingController::class, 'partnerBookings']);
        Route::get('/bookings/stats',         [BookingController::class, 'partnerBookingStats']);
        Route::post('/bookings/{id}/confirm', [BookingController::class, 'confirmPartnerBooking']);
        Route::post('/bookings/{id}/reject',  [BookingController::class, 'rejectPartnerBooking']);

        // 🆕 Earnings
        Route::get('/earnings', [EarningsController::class, 'index']);

        // 🆕 Payouts
        Route::get('/payouts',          [PayoutController::class, 'partnerIndex']);
        Route::post('/payouts/request', [PayoutController::class, 'requestPayout']);

        // 🆕 Bank Accounts
        Route::get('/bank-accounts',         [PayoutController::class, 'bankAccounts']);
        Route::post('/bank-accounts',        [PayoutController::class, 'addBankAccount']);
        Route::delete('/bank-accounts/{id}', [PayoutController::class, 'removeBankAccount']);
    });

    // ============================================
    // ADMIN (የተለየ ቡድን - ራሱን የቻለ)
    // ============================================
    Route::middleware('admin')->prefix('admin')->group(function () {

        Route::get('/dashboard', [AdminDashboardController::class, 'index']);

        Route::get('/users',               [UserController::class, 'index']);
        Route::get('/users/{id}',          [UserController::class, 'show']);
        Route::patch('/users/{id}/role',   [UserController::class, 'updateRole']);
        Route::patch('/users/{id}/status', [UserController::class, 'updateStatus']);

        Route::get('/venues',                  [AdminApprovalController::class, 'index']);
        Route::get('/approvals/pending',       [AdminApprovalController::class, 'pendingVenues']);
        Route::post('/approvals/{id}/approve', [AdminApprovalController::class, 'approveVenue']);
        Route::post('/approvals/{id}/reject',  [AdminApprovalController::class, 'rejectVenue']);

        Route::get('/bookings-list',            [BookingController::class, 'adminIndex']);
        Route::post('/bookings/{id}/confirm',   [BookingController::class, 'confirm']);
        Route::post('/bookings/{id}/reject',    [BookingController::class, 'reject']);

        // 🎯 Admin Payouts (different controller!)
        Route::get('/wallet',                 [AdminPayoutController::class, 'wallet']);
        Route::get('/payouts',                [AdminPayoutController::class, 'index']);
        Route::patch('/payouts/{id}/approve', [AdminPayoutController::class, 'approve']);
        Route::patch('/payouts/{id}/reject',  [AdminPayoutController::class, 'reject']);

        Route::get('/settings',              [SettingsController::class, 'index']);
        Route::post('/settings',             [SettingsController::class, 'update']);
        Route::post('/settings/clear-cache', [SettingsController::class, 'clearCache']);
        Route::get('/settings/backup',       [SettingsController::class, 'backup']);

        Route::post('/profile', [AdminProfileController::class, 'update']);
        Route::get('/reports', [ReportController::class, 'index']);
    });

    // ── USER (የተለየ ቡድን - ራሱን የቻለ) ──
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