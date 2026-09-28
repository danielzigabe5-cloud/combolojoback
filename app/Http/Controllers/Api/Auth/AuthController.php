<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    // ============================================================
    // HELPER — Format user response (WITH FULL AVATAR URL)
    // ============================================================
    private function formatUserResponse($user)
    {
        if (!$user) {
            return null;
        }

        $isProfileComplete = !empty($user->name)
                          && strlen($user->name) > 2
                          && !empty($user->password);

        // ✅ Build a FULL avatar URL
        $avatarUrl = null;
        if (!empty($user->avatar)) {
            $raw = $user->avatar;

            // Already a full URL
            if (str_starts_with($raw, 'http://') ||
                str_starts_with($raw, 'https://') ||
                str_starts_with($raw, 'data:')) {
                $avatarUrl = $raw;
            } else {
                // Relative path → prepend storage URL
                $avatarUrl = asset('storage/' . ltrim($raw, '/'));
            }
        }

        return [
            'id'                  => $user->id,
            'name'                => $user->name,
            'email'               => $user->email,
            'phone'               => $user->phone_number ?? null,
            'phone_number'        => $user->phone_number ?? null,
            'role'                => $user->role ?? 'user',
            'avatar'              => $avatarUrl,          // ✅ full URL
            'avatar_url'          => $avatarUrl,          // ✅ alias for compat
            'is_profile_complete' => $isProfileComplete,
            'email_verified_at'   => $user->email_verified_at,
            'created_at'          => $user->created_at,
        ];
    }

    // ============================================================
    // 1. LOGIN
    // ============================================================
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {
            $user = User::where('email', trim($request->email))->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                ], 404);
            }

            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid password. Please try again.',
                ], 401);
            }

            $token = $user->createToken('auth_token')->plainTextToken;
            $user->update(['last_login_at' => now()]);

            return response()->json([
                'success'     => true,
                'message'     => 'Login successful!',
                'next_screen' => 'home',
                'data'        => [
                    'token' => $token,
                    'user'  => $this->formatUserResponse($user),
                    'role'  => $user->role ?? 'user',
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Login error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ============================================================
    // 2. SEND OTP
    // ============================================================
    public function sendOTP(Request $request)
    {
        $email = trim($request->email);
        $phone = trim($request->phone);

        $userByEmail = User::where('email', $email)->first();
        if ($userByEmail && !empty($userByEmail->password)) {
            return response()->json([
                'success'     => true,
                'message'     => 'Account already exists. Please login.',
                'next_screen' => 'login'
            ]);
        }

        $otp = rand(100000, 999999);
        User::updateOrCreate(
            ['email' => $email],
            [
                'phone_number'   => $phone,
                'otp_code'       => $otp,
                'otp_expires_at' => now()->addMinutes(10)
            ]
        );

        Log::info("OTP for $email: $otp");

        return response()->json([
            'success'     => true,
            'message'     => 'OTP sent successfully',
            'next_screen' => 'verify_otp',
            'data'        => ['email' => $email],
        ]);
    }

    // ============================================================
    // 3. VERIFY OTP
    // ============================================================
    public function verifyOTP(Request $request)
    {
        $user = User::where('email', $request->email)
                    ->where('otp_code', $request->otp)
                    ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP code'
            ], 401);
        }

        if ($user->otp_expires_at && now()->greaterThan($user->otp_expires_at)) {
            return response()->json([
                'success' => false,
                'message' => 'OTP has expired. Please request a new one.'
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        $isComplete = !empty($user->phone_number) && !empty($user->name);

        $user->update(['otp_code' => null, 'otp_expires_at' => null]);

        return response()->json([
            'success'     => true,
            'message'     => 'Verification successful',
            'next_screen' => $isComplete ? 'home' : 'complete_profile',
            'data'        => [
                'token' => $token,
                'user'  => $this->formatUserResponse($user),
            ]
        ]);
    }

    // ============================================================
    // 4. COMPLETE PROFILE
    // ============================================================
    public function completeProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $user = $request->user();
        $user->update([
            'name'     => $request->name,
            'password' => Hash::make($request->password),
        ]);

        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success'     => true,
            'message'     => 'Profile completed successfully',
            'next_screen' => 'home',
            'data'        => [
                'token'               => $token,
                'user'                => $this->formatUserResponse($user),
                'is_profile_complete' => true,
            ]
        ]);
    }

    // ============================================================
    // 5. GET ME
    // ============================================================
    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'data'    => $this->formatUserResponse($request->user()),
            'user'    => $this->formatUserResponse($request->user()), // ✅ compat
        ]);
    }

    // ============================================================
    // 6. LOGOUT
    // ============================================================
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    // ============================================================
    // 7. GOOGLE LOGIN
    // ============================================================
    public function googleLogin(Request $request)
    {
        // ----- 1. VALIDATION -----
        $validator = Validator::make($request->all(), [
            'id_token' => 'required|string',
            'email'    => 'nullable|email',
            'name'     => 'nullable|string|max:255',
            'photo'    => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        try {
            $idToken = $request->id_token;

            Log::info('🔵 Google login request received');

            // ----- 2. VERIFY WITH SHORT TIMEOUT -----
            try {
                $googleResponse = Http::timeout(5)
                    ->connectTimeout(3)
                    ->withOptions(['verify' => false])
                    ->get('https://oauth2.googleapis.com/tokeninfo', [
                        'id_token' => $idToken,
                    ]);
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                Log::error('❌ Google connection failed', [
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Cannot reach Google. Please check your internet.',
                ], 504);
            }

            if (!$googleResponse->successful()) {
                Log::warning('❌ Google token invalid', [
                    'status' => $googleResponse->status(),
                    'body'   => $googleResponse->body(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Google token. Please try again.',
                ], 401);
            }

            $payload = $googleResponse->json();

            Log::info('✅ Google token verified', [
                'email' => $payload['email'] ?? 'unknown',
            ]);

            // ----- 3. VERIFY AUDIENCE -----
            $clientId = config('services.google.client_id');
            $aud = $payload['aud'] ?? '';

            if ($clientId && $aud !== $clientId) {
                Log::warning('❌ Audience mismatch', [
                    'expected' => $clientId,
                    'received' => $aud,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Google configuration mismatch',
                ], 401);
            }

            // ----- 4. EXTRACT USER INFO -----
            $googleId = $payload['sub'] ?? null;
            $email    = $payload['email'] ?? null;
            $name     = $payload['name'] ?? $request->name ?? 'Google User';
            $picture  = $payload['picture'] ?? $request->photo;
            $verified = $payload['email_verified'] ?? false;

            if (!$googleId || !$email) {
                return response()->json([
                    'success' => false,
                    'message' => 'Google token missing required information',
                ], 401);
            }

            if (!$verified) {
                return response()->json([
                    'success' => false,
                    'message' => 'Google email is not verified',
                ], 401);
            }

            // ----- 5. FIND OR CREATE USER -----
            $user = User::where('email', $email)->first();
            $isNewUser = false;

            if (!$user) {
                $user = User::create([
                    'name'              => $name,
                    'email'             => $email,
                    'google_id'         => $googleId,
                    'avatar'            => $picture,
                    'password'          => Hash::make(Str::random(32)),
                    'email_verified_at' => now(),
                    'phone_number'      => null,
                    'role'              => 'user',
                ]);
                $isNewUser = true;

                Log::info('✅ New user via Google', [
                    'user_id' => $user->id,
                    'email'   => $email,
                ]);
            } else {
                $user->update([
                    'google_id' => $googleId,
                    'avatar'    => $picture ?? $user->avatar,
                ]);

                Log::info('✅ Existing user via Google', [
                    'user_id' => $user->id,
                    'email'   => $email,
                ]);
            }

            // ----- 6. CREATE TOKEN -----
            $user->tokens()->delete();
            $token = $user->createToken('google_auth_token')->plainTextToken;
            $user->update(['last_login_at' => now()]);

            // ----- 7. SUCCESS RESPONSE -----
            return response()->json([
                'success'     => true,
                'message'     => $isNewUser
                                    ? 'Account created successfully'
                                    : 'Login successful',
                'next_screen' => 'home',
                'data'        => [
                    'token'               => $token,
                    'user'                => $this->formatUserResponse($user),
                    'role'                => $user->role ?? 'user',
                    'email'               => $user->email,
                    'is_profile_complete' => true,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('❌ Google login error', [
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Google authentication failed: ' . $e->getMessage(),
            ], 401);
        }
    }
}