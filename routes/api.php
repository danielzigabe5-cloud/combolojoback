<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Dashboard\AdminDashboardController;
use App\Http\Controllers\Api\Dashboard\OwnerDashboardController;
use App\Http\Controllers\Api\Dashboard\UserDashboardController;
use App\Http\Controllers\VenueController;
use App\Http\Controllers\Api\Resources\EventController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\Resources\GameController;
use App\Http\Controllers\Api\Admin\AdminApprovalController;
use App\Http\Controllers\Api\PartnerDashboardController; 
use App\Http\Controllers\Api\PartnerSettingsController; // የቅንብር ኮንትሮለር
use App\Http\Controllers\Api\PayoutController;          // የክፍያ ኮንትሮለር
use App\Http\Controllers\Api\ScheduleController;        // የቀን መርሃግብር ኮንትሮለር
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return response()->json([
        'success' => true,
        'message' => 'Backend is working correctly! Status 200 OK'
    ]);
});

// ============================================
// AUTH ROUTES (Login, Register, OTP)
// ============================================
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/send-otp', [AuthController::class, 'sendOTP']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOTP']);
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/complete-profile', [AuthController::class, 'completeProfile']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

// ============================================
// PUBLIC ROUTES (No Login required)
// ============================================
Route::get('/venues', [VenueController::class, 'index']);
Route::get('/venues/{id}', [VenueController::class, 'show']);
Route::get('/events', [EventController::class, 'index']);
Route::get('/games', [GameController::class, 'index']);

Route::get('/check-availability', [BookingController::class, 'checkAvailability']);
Route::get('/available-time-slots', [BookingController::class, 'getAvailableTimeSlots']);

// ============================================
// PROTECTED ROUTES (Authentication required)
// ============================================
Route::middleware(['auth:sanctum'])->group(function () {

    // Venue & General Booking
    Route::post('/venues', [VenueController::class, 'store']);
    Route::get('/my-venues', [VenueController::class, 'myVenues']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/my-bookings', [BookingController::class, 'myBookings']);
    Route::get('/bookings/{id}', [BookingController::class, 'show']);
    
    // ============================================
    // ADMIN ROUTES (Admin only)
    // ============================================
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index']);
        Route::get('/users', [AdminDashboardController::class, 'users']);
        Route::get('/venues', [AdminApprovalController::class, 'index']); 
        Route::get('/approvals/pending', [AdminApprovalController::class, 'pendingVenues']);
        Route::post('/approvals/{id}/approve', [AdminApprovalController::class, 'approveVenue']);
        Route::post('/approvals/{id}/reject', [AdminApprovalController::class, 'rejectVenue']);
        Route::get('/bookings-list', [BookingController::class, 'adminIndex']); 
        Route::post('/bookings/{id}/confirm', [BookingController::class, 'confirm']); 
        Route::post('/bookings/{id}/reject', [BookingController::class, 'reject']);
    });

    // ============================================
    // OWNER (PARTNER) ROUTES
    // ============================================
    Route::middleware('owner')->prefix('owner')->group(function () {
        // 1. Overview & Dashboard
        Route::get('/dashboard', [OwnerDashboardController::class, 'index']);
        Route::get('/overview', [PartnerDashboardController::class, 'getOverview']);

        // 2. Schedule (የቀን መርሃግብር)
        Route::get('/schedule/venues', [ScheduleController::class, 'getVenues']);
        Route::get('/schedule/slots', [ScheduleController::class, 'getSchedule']);
        Route::post('/schedule/toggle-block', [ScheduleController::class, 'toggleBlock']);

        // 3. Payouts (የክፍያ ታሪክ እና ወጪ ማድረጊያ)
        Route::get('/payouts', [PayoutController::class, 'index']);
        Route::post('/withdraw', [PayoutController::class, 'withdraw']);

        // 4. Settings (የመለያ ቅንብሮች)
        Route::prefix('settings')->group(function () {
            Route::put('/profile', [PartnerSettingsController::class, 'updateProfile']);
            Route::put('/bank', [PartnerSettingsController::class, 'updateBank']);
            Route::put('/password', [PartnerSettingsController::class, 'updatePassword']);
        });
    });

    // USER ROUTES
    Route::middleware('user')->prefix('user')->group(function () {
        Route::get('/dashboard', [UserDashboardController::class, 'index']);
    });
});