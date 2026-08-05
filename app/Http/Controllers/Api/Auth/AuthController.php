<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Hash, Validator, Auth};

class AuthController extends Controller
{
    private function formatUserResponse($user)
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone_number' => $user->phone_number,
            'isProfileComplete' => !empty($user->password) && !empty($user->name),
        ];
    }
public function sendOTP(Request $request)
{
    // 1. ስልክ ቁጥሩ በሌላ ኢሜይል መያዙን መጀመሪያ ቼክ እናድርግ
    $existingUserWithPhone = User::where('phone_number', $request->phone)
                                 ->where('email', '!=', $request->email)
                                 ->first();

    if ($existingUserWithPhone) {
        return response()->json([
            'success' => false,
            'message' => 'ይህ ስልክ ቁጥር ቀድሞ በሌላ ኢሜይል ተመዝግቧል'
        ], 400);
    }

    $user = User::where('email', $request->email)->first();
    $next = ($user && !empty($user->password)) ? 'login' : 'verify_otp';

    if ($next === 'verify_otp') {
        $otp = rand(100000, 999999);
        
        // updateOrCreate በስህተት Duplicate እንዳይፈጥር እንዲህ እናድርገው
        User::updateOrCreate(
            ['email' => $request->email],
            [
                'phone_number' => $request->phone, 
                'otp_code' => $otp, 
                'otp_expires_at' => now()->addMinutes(10)
            ]
        );
        \Log::info("OTP: $otp");
    }

    return response()->json([
        'success' => true,
        'message' => 'Success',
        'next_screen' => $next,
        'data' => ['email' => $request->email],
        
        
    ]);
}

    public function verifyOTP(Request $request)
    {
        $user = User::where('email', $request->email)->where('otp_code', $request->otp)->first();
        if (!$user) return response()->json(['success' => false, 'message' => 'Invalid OTP'], 401);

        $token = $user->createToken('auth_token')->plainTextToken;
        $isComplete = !empty($user->password) && !empty($user->name);

        return response()->json([
            'success' => true,
            'message' => 'Verified',
            'next_screen' => $isComplete ? 'home' : 'complete_profile',
            'data' => [
                'token' => $token,
                'user' => $this->formatUserResponse($user),
                'is_profile_complete' => $isComplete
            ]
        ]);
    }
    public function completeProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $user = $request->user(); // Sanctum token ተጠቅሞ ተጠቃሚውን ያገኘዋል

        $user->update([
            'name' => $request->name,
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Profile completed successfully',
            'data' => [
                'user' => $this->formatUserResponse($user)
            ]
        ]);
    }
}