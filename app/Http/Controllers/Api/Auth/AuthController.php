<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\OTPMail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\NumberParseException;

class AuthController extends Controller
{
    /**
     * ስልክ ቁጥር ማረጋገጫ
     */
    private function validatePhoneNumber($phone, $countryCode = null)
    {
        $phone = preg_replace('/\s+/', '', $phone);
        
        if (!str_starts_with($phone, '+')) {
            if ($countryCode && !str_starts_with($phone, $countryCode)) {
                $phone = $countryCode . $phone;
            } else {
                $phone = '+' . $phone;
            }
        }

        try {
            $phoneUtil = PhoneNumberUtil::getInstance();
            $numberProto = $phoneUtil->parse($phone, null);
            
            if (!$phoneUtil->isValidNumber($numberProto)) {
                return ['valid' => false, 'message' => 'Invalid phone number format'];
            }

            return [
                'valid' => true,
                'phone' => $phoneUtil->format($numberProto, PhoneNumberFormat::E164),
                'country_code' => $phoneUtil->getRegionCodeForNumber($numberProto),
                'national_number' => $phoneUtil->format($numberProto, PhoneNumberFormat::NATIONAL),
                'international_number' => $phoneUtil->format($numberProto, PhoneNumberFormat::INTERNATIONAL)
            ];
        } catch (NumberParseException $e) {
            return ['valid' => false, 'message' => 'Invalid phone number: ' . $e->getMessage()];
        }
    }

    /**
     * POST /api/auth/send-otp
     */
    public function sendOTP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'phone' => 'required|string',
            'countryCode' => 'nullable|string',
            'countryIso' => 'nullable|string|size:2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $phoneValidation = $this->validatePhoneNumber(
            $request->phone, 
            $request->countryCode ?? '+251'
        );
        
        if (!$phoneValidation['valid']) {
            return response()->json([
                'success' => false,
                'message' => $phoneValidation['message'] ?? 'Invalid phone number format'
            ], 422);
        }

        $otp = rand(100000, 999999);

        $user = User::updateOrCreate(
            ['email' => $request->email],
            [
                'phone_number' => $phoneValidation['phone'],
                'phone_country_code' => $phoneValidation['country_code'] ?? 'ET',
                'phone_country_iso' => $request->countryIso ?? 'ET',
                'otp_code' => $otp,
                'otp_expires_at' => Carbon::now()->addMinutes(10),
                'otp_attempts' => 0,
                'otp_last_attempt_at' => null,
            ]
        );

        try {
            Mail::to($user->email)->send(new OTPMail($otp));
            
            return response()->json([
                'success' => true,
                'message' => 'OTP sent successfully to your email.',
                'data' => [
                    'email' => $user->email,
                    'phone' => $phoneValidation['phone'],
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send email. Please check your email settings.'
            ], 500);
        }
    }

    /**
     * POST /api/auth/verify-otp
     */
    public function verifyOTP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp' => 'required|numeric|digits:6',
            'phone' => 'nullable|string',
            'countryCode' => 'nullable|string',
            'countryIso' => 'nullable|string|size:2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.'
            ], 404);
        }

        if (!$user->canAttemptOTP()) {
            return response()->json([
                'success' => false,
                'message' => 'Too many failed attempts. Please try again after 30 minutes.'
            ], 429);
        }

        if (!$user->hasValidOTP() || $user->otp_code != $request->otp) {
            $user->incrementOTPAttempts();
            $remainingAttempts = 5 - $user->otp_attempts;
            return response()->json([
                'success' => false,
                'message' => "Invalid OTP. You have {$remainingAttempts} attempts remaining."
            ], 401);
        }

        $user->email_verified_at = Carbon::now();
        $user->phone_verified_at = Carbon::now();
        $user->clearOTP();

        $isProfileComplete = $user->isProfileComplete();
        $token = $user->createToken('auth_token', ['*'], Carbon::now()->addDays(7))->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'role' => $user->role,
                'isUser' => $user->isUser(),
                'isAdmin' => $user->isAdmin(),
                'isProfileComplete' => $isProfileComplete,
                'isEmailVerified' => $user->isEmailVerified(),
                'isPhoneVerified' => $user->isPhoneVerified(),
            ],
            'is_profile_complete' => $isProfileComplete,
            'next_screen' => $isProfileComplete ? 'home' : 'complete_profile',
        ]);
    }

    /**
     * POST /api/auth/complete-profile
     */
    public function completeProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|min:3',
            'password' => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $user = $request->user();

        if ($user->isProfileComplete()) {
            return response()->json([
                'success' => false,
                'message' => 'Profile already completed.'
            ], 400);
        }

        $user->name = $request->name;
        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Profile completed successfully.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'role' => $user->role,
                'isProfileComplete' => true,
                'isUser' => $user->isUser(),
            ],
            'is_profile_complete' => true,
            'next_screen' => 'home',
        ]);
    }

    /**
     * POST /api/auth/login
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password.'
            ], 401);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        
        $role = $user->role ?? 'user';
        $isUser = ($role === 'user' || $role === null);
        
        if (!$isUser) {
            return response()->json([
                'success' => false,
                'message' => 'This app is for users only.'
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'role' => $role,
                'isUser' => $isUser,
                'isProfileComplete' => $user->isProfileComplete(),
            ],
            'is_profile_complete' => $user->isProfileComplete(),
        ]);
    }

    /**
     * GET /api/auth/me
     */
    public function me(Request $request)
    {
        $user = $request->user();
        $role = $user->role ?? 'user';
        
        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'role' => $role,
                'isUser' => ($role === 'user' || $role === null),
                'isAdmin' => ($role === 'admin'),
                'isProfileComplete' => $user->isProfileComplete(),
                'isEmailVerified' => $user->isEmailVerified(),
                'isPhoneVerified' => $user->isPhoneVerified(),
                'created_at' => $user->created_at,
            ]
        ]);
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.'
        ]);
    }
}