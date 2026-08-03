<?php

use App\Http\Controllers\Api\Auth\AuthController;
use Illuminate\Support\Facades\Route;

// ============================================================
// ===== ለሁሉም ክፍት የሆኑ ሩቶች =====
// ============================================================
Route::post('/auth/send-otp', [AuthController::class, 'sendOTP']);
Route::post('/auth/verify-otp', [AuthController::class, 'verifyOTP']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/validate-phone', [AuthController::class, 'validatePhone']);
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);

// ============================================================
// ===== በSanctum የሚጠበቁ ሩቶች (ቶከን ያስፈልጋል) =====
// ============================================================
Route::middleware(['auth:sanctum'])->group(function () {
    // መገለጫ ሙሌት (አዲስ ተጠቃሚ)
    Route::post('/auth/complete-profile', [AuthController::class, 'completeProfile']);
    
    // መገለጫ ማስተካከያ (ነባር ተጠቃሚ)
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::patch('/auth/profile', [AuthController::class, 'updateProfile']);
    
    // የይለፍ ቃል መቀየር
    Route::post('/auth/change-password', [AuthController::class, 'changePassword']);
    
    // የአሁኑን ተጠቃሚ ማግኘት
    Route::get('/auth/me', [AuthController::class, 'me']);
    
    // መውጣት
    Route::post('/auth/logout', [AuthController::class, 'logout']);
});