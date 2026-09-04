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
            'role' => $user->role ?? 'user', // 👈 ይህን አስፈላጊ መስመር ይጨምሩ!
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
        'email.exists' => 'This Email Is Not Registered. please register first.',
        'email.required' => 'Required Email',
        'password.required' => 'Required Password'
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

        // 3. ፓስዎርድ ማረጋገጥ
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Credential!'
            ], 401);
        }

        // ✅ 4. አሁን ተጠቃሚው መሆኑ ስለታወቀ last_login_at አፕዴት እናደርጋለን
        $user->update([
            'last_login_at' => now()
        ]);

        // 5. Token መፍጠር
        $token = $user->createToken('auth_token')->plainTextToken;

        // 6. የAccess Logic
        $canUseMobile = ($user->role === 'user'); 
        $canUseWeb = true; 

        return response()->json([
            'success' => true,
            'message' => 'login successfully!',
            'data' => [
                'token' => $token,
                'user' => $this->formatUserResponse($user),
                'role' => $user->role ?? 'user',
                'access' => [
                    'mobile_app' => $canUseMobile,
                    'web_portal' => $canUseWeb
                ]
            ]
        ], 200);

    } catch (\Exception $e) {
        \Log::error('Login error: ' . $e->getMessage()); // ስህተቱን በሎግ እንይ
        return response()->json([
            'success' => false,
            'message' => 'internal server error, please try again later.'
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