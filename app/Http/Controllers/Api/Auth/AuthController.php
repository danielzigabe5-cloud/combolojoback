<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Hash, Validator, Auth};

class AuthController extends Controller
{
    /**
     * ተጠቃሚው ሲገባ የሚመለስ ዳታ ፎርማት
     */
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

    public function login(Request $request)
{
    // 1. ጥብቅ ቫሊዴሽን
    $validator = Validator::make($request->all(), [
        'email' => 'required|email|exists:users,email',
        'password' => 'required|min:6',
    ], [
        'email.exists' => 'ይህ ኢሜይል አልተመዘገበም። እባክዎ መጀመሪያ ይመዝገቡ።',
        'email.required' => 'ኢሜይል ያስፈልጋል',
        'password.required' => 'ፓስዎርድ ያስፈልጋል'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false, 
            'message' => $validator->errors()->first()
        ], 422);
    }

    try {
        // 2. ተጠቃሚውን መፈለግ
        $user = User::where('email', $request->email)->first();

        // 3. ፓስዎርድ በትክክል መኖሩን እና መመሳሰሉን ማረጋገጥ
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'የገቡት ኢሜይል ወይም ፓስዎርድ የተሳሳተ ነው።'
            ], 401);
        }

        // 4. Token መፍጠር
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'በተሳካ ሁኔታ ገብተዋል!',
            'data' => [
                'token' => $token,
                'user' => $this->formatUserResponse($user)
            ]
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'የሰርቨር ስህተት አጋጥሟል፤ እባክዎ ቆይተው ይሞክሩ።'
        ], 500);
    }
}

    /**
     * 2. SEND OTP - ለምዝገባ (Registration)
     */
    public function sendOTP(Request $request)
    {
        $email = trim($request->email);
        $phone = trim($request->phone);

        // አካውንቱ ቀድሞ ካለ ወደ Login እንዲሄዱ ንገራቸው
        $userByEmail = User::where('email', $email)->first();
        if ($userByEmail && !empty($userByEmail->password)) {
            return response()->json([
                'success' => true,
                'message' => 'Account already exists. Please login.',
                'next_screen' => 'login'
            ]);
        }

        $otp = rand(100000, 999999);
        User::updateOrCreate(
            ['email' => $email],
            ['phone_number' => $phone, 'otp_code' => $otp, 'otp_expires_at' => now()->addMinutes(10)]
        );

        \Log::info("OTP for $email: $otp");

        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully',
            'next_screen' => 'verify_otp',
            'data' => ['email' => $email],
        ]);
    }

    /**
     * 3. VERIFY OTP
     */
    public function verifyOTP(Request $request)
    {
        $user = User::where('email', $request->email)->where('otp_code', $request->otp)->first();
        
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Invalid OTP code'], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        $isComplete = !empty($user->password) && !empty($user->name);

        return response()->json([
            'success' => true,
            'message' => 'Verification successful',
            'next_screen' => $isComplete ? 'home' : 'complete_profile',
            'data' => [
                'token' => $token,
                'user' => $this->formatUserResponse($user),
            ]
        ]);
    }

    /**
     * 4. COMPLETE PROFILE (የመጀመሪያ ምዝገባ ሲጠናቀቅ)
     */
    public function completeProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $user = $request->user();
        $user->update([
            'name' => $request->name,
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Profile completed successfully',
            'data' => ['user' => $this->formatUserResponse($user)]
        ]);
    }

    /**
     * 5. GET ME - የገባውን ተጠቃሚ ዳታ ለማምጣት
     */
    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $this->formatUserResponse($request->user())
        ]);
    }

    /**
     * 6. LOGOUT
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['success' => true, 'message' => 'Logged out successfully']);
    }
}