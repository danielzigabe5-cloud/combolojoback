<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

// Test Route
Route::get('/test', function () {
    return response()->json([
        'message' => 'Compolojo API is running!',
        'version' => '1.0.0',
        'status' => 'OK'
    ]);
});

// Auth Routes - Public
Route::prefix('auth')->group(function () {
    Route::post('/send-otp', [AuthController::class, 'sendOTP']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOTP']);
    Route::post('/check-email', [AuthController::class, 'checkEmail']);
});

// Auth Routes - Protected (Sanctum)
Route::middleware(['auth:sanctum'])->prefix('auth')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/complete-profile', [AuthController::class, 'completeProfile']);
    Route::post('/logout', [AuthController::class, 'logout']);
});
