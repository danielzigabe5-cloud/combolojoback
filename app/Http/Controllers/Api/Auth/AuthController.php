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
    // AuthController.php ውስጥ
private function formatUserResponse($user)
{
    return [
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'phone' => $user->phone, // 👈 'phone_number' የነበረውን ወደ 'phone' ቀይረው
        'role' => $user->role ?? 'user',
        'isProfileComplete' => !empty($user->password) && !empty($user->name),
    ];
}
    

  public function login(Request $request)
{
    // 1. ቫሊዴሽን
    $validator = Validator::make($request->all(), [
        'email' => 'required|email',
        'password' => 'required|min:6',
    ]);

    if ($validator->fails()) {
        return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
    }

    $start = microtime(true); // ሰዓት መቁጠር ጀመረ

    try {
        // 2. ተጠቃሚውን መፈለግ (አንዴ ብቻ)
        $user = User::where('email', trim($request->email))->first();
        
        $dbTime = microtime(true) - $start;
        \Log::info("Login DB Search for {$request->email} took: {$dbTime} seconds");

        // ኢሜይሉ ዳታቤዝ ውስጥ ከሌለ
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'This account is not registered. Please register first.'
            ], 404);
        }

        // ምዝገባው ካልተጠናቀቀ (ፓስዎርድ ከሌለው)
        if (empty($user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Account exists but registration is incomplete. Please register again to get OTP.'
            ], 403);
        }

        // 3. ፓስዎርድ ማረጋገጥ
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid password. Please try again.'
            ], 401);
        }

        // 4. Token መፍጠር (Sanctum)
        $token = $user->createToken('auth_token')->plainTextToken;
        
        $user->update(['last_login_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Login successful!',
            'data' => [
                'token' => $token,
                'user' => $this->formatUserResponse($user),
                'role' => $user->role ?? 'user'
            ]
        ], 200);

    } catch (\Exception $e) {
        \Log::error('Login error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Internal server error. Please try again later.'
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
                'user' => $this->formatUserResponse($user), // 👈 role እዚህ ይገኛል
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
            'password' => 'required|string|min:6|confirmed',
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
            'data' => ['user' => $this->formatUserResponse($user)] // 👈 role እዚህ ይገኛል
        ]);
    }

    /**
     * 5. GET ME - የገባውን ተጠቃሚ ዳታ ለማምጣት
     */
    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $this->formatUserResponse($request->user()) // 👈 role እዚህ ይገኛል
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