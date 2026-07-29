<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\OtpVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use App\Mail\OtpMail;

class AuthController extends Controller
{
    /**
     * 1. SEND OTP TO EMAIL
     * POST /api/auth/send-otp
     */
    public function sendOTP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'country_code' => 'required|string|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $email = $request->email;
            $countryCode = $request->country_code;

            // Generate 6-digit OTP
            $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            // Delete old OTP
            OtpVerification::where('email', $email)->delete();

            // Store new OTP
            OtpVerification::create([
                'email' => $email,
                'otp' => $otp,
                'country_code' => $countryCode,
                'expires_at' => now()->addMinutes(10),
                'is_verified' => false,
            ]);

            // Send OTP via Email
            Mail::to($email)->send(new OtpMail($otp));

            return response()->json([
                'success' => true,
                'message' => 'OTP sent successfully to your email',
                'data' => [
                    'email' => $email,
                    'country_code' => $countryCode,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send OTP',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 2. VERIFY OTP AND LOGIN/REGISTER
     * POST /api/auth/verify-otp
     */
    public function verifyOTP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'otp' => 'required|string|size:6',
            'country_code' => 'required|string|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $email = $request->email;
            $otp = $request->otp;
            $countryCode = $request->country_code;

            // Find OTP record
            $otpRecord = OtpVerification::where('email', $email)
                ->where('otp', $otp)
                ->where('is_verified', false)
                ->first();

            if (!$otpRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid OTP'
                ], 400);
            }

            // Check if OTP is expired
            if (now()->gt($otpRecord->expires_at)) {
                return response()->json([
                    'success' => false,
                    'message' => 'OTP has expired. Please request a new one.'
                ], 400);
            }

            // Mark OTP as verified
            $otpRecord->is_verified = true;
            $otpRecord->save();

            // Find or create user
            $user = User::where('email', $email)->first();

            if (!$user) {
                $user = User::create([
                    'email' => $email,
                    'country_code' => $countryCode,
                    'role' => 'user',
                    'is_active' => true,
                ]);
            } else {
                $user->country_code = $countryCode;
                $user->save();
            }

            // Create Sanctum Token
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'User authenticated successfully',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'email' => $user->email,
                        'name' => $user->name,
                        'phone' => $user->phone,
                        'country_code' => $user->country_code,
                        'role' => $user->role,
                        'is_active' => $user->is_active,
                    ],
                    'token' => $token,
                    'token_type' => 'Bearer',
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify OTP',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 3. COMPLETE USER PROFILE
     * POST /api/auth/complete-profile
     */
    public function completeProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::where('email', $request->email)->first();
            
            $fullName = trim($request->first_name . ' ' . ($request->last_name ?? ''));
            $user->name = $fullName;
            
            if ($request->phone) {
                $user->phone = $request->phone;
            }
            
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Profile completed successfully',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'email' => $user->email,
                        'name' => $user->name,
                        'phone' => $user->phone,
                        'country_code' => $user->country_code,
                        'role' => $user->role,
                    ],
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 4. GET AUTHENTICATED USER
     * GET /api/auth/me
     */
    public function me(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'email' => $user->email,
                        'name' => $user->name,
                        'phone' => $user->phone,
                        'country_code' => $user->country_code,
                        'role' => $user->role,
                        'is_active' => $user->is_active,
                    ]
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 5. LOGOUT USER
     * POST /api/auth/logout
     */
    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Successfully logged out'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to logout',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 6. CHECK EMAIL EXISTS
     * POST /api/auth/check-email
     */
    public function checkEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $exists = User::where('email', $request->email)->exists();

        return response()->json([
            'success' => true,
            'data' => [
                'email' => $request->email,
                'exists' => $exists,
            ]
        ], 200);
    }
}
