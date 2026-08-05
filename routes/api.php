<?php

use App\Http\Controllers\Api\Auth\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| ይህ Route http://172.29.51.75:8000/api ሲጠራ Status 200 እንዲሰጥ ያደርጋል
*/
Route::get('/', function () {
    return response()->json([
        'success' => true,
        'message' => 'Backend is working correctly! Status 200 OK'
    ]);
});

// የ Authentication መንገዶች (http://172.29.51.75:8000/api/auth/...)
Route::prefix('auth')->group(function () {
    
    // 1. OTP ለመላክ
    Route::post('/send-otp', [AuthController::class, 'sendOTP']);
    
    // 2. OTP ለማረጋገጥ
    Route::post('/verify-otp', [AuthController::class, 'verifyOTP']);
    
    // 3. በቀጥታ በፓስዎርድ ለመግባት (Registered ለሆኑ)
    Route::post('/login', [AuthController::class, 'login']);

    // ቶክን ለሚፈልጉ መንገዶች (Login ካደረጉ በኋላ)
    Route::middleware('auth:sanctum')->group(function () {
        // 4. ፕሮፋይል ለማሟላት
        Route::post('/complete-profile', [AuthController::class, 'completeProfile']);
        // 5. የተጠቃሚ መረጃ ለማግኘት
        Route::get('/me', [AuthController::class, 'me']);
        // 6. ለመውጣት
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});