<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Hash, Validator, Log};
use Illuminate\Support\Str;
use Google\Client as GoogleClient;

class AuthController extends Controller
{
    /**
     * ተጠቃሚው ሲገባ የሚመለስ ዳታ ፎርማት
     */
    private function formatUserResponse($user)
    {
        // ✅ የተስተካከለ ስሌት
        // Name እና password ካሉ profile complete ነው
        // (Phone ለ Google ተጠቃሚ አያስፈልግም)
        $isProfileComplete = !empty($user->name)
                          && strlen($user->name) > 2
                          && !empty($user->password);
        
        // ✅ ወይም profile_complete column ካለዎ ይጠቀሙ
        // $isProfileComplete = $user->profile_complete ?? false;

        return [
            'id'                  => $user->id,
            'name'                => $user->name,
            'email'               => $user->email,
            'phone'               => $user->phone_number ?? null,
            'role'                => $user->role ?? 'user',
            'avatar'              => $user->avatar ?? null,
            'is_profile_complete' => $isProfileComplete,
        ];
    }

    // ============================================================
    // 1. LOGIN (Email + Password)
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
        
        // ✅ Phone ካለ → home, ካልሆነ → complete_profile
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

        // ✅ አዲስ token ያዙሩ
        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success'     => true,
            'message'     => 'Profile completed successfully',
            'next_screen' => 'home',  // ✅ ይህን ያክሉ
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
            'data'    => $this->formatUserResponse($request->user())
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
    // 7. GOOGLE LOGIN — ✅ ቀጥታ ወደ HOME ይሂድ
    // ============================================================
    public function googleLogin(Request $request)
    {
        // ----- 1. VALIDATION -----
        $validator = Validator::make($request->all(), [
            'id_token'     => 'required|string',
            'access_token' => 'nullable|string',
            'email'        => 'nullable|email',
            'name'         => 'nullable|string|max:255',
            'photo'        => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        try {
            // ----- 2. VERIFY GOOGLE ID TOKEN -----
            $clientId = config('services.google.client_id');

            if (empty($clientId)) {
                Log::error('GOOGLE_CLIENT_ID is not configured in .env');
                return response()->json([
                    'success' => false,
                    'message' => 'Server configuration error: Google Client ID missing',
                ], 500);
            }

            $client = new GoogleClient(['client_id' => $clientId]);
            $payload = $client->verifyIdToken($request->id_token);

            // ----- DEBUG LOG -----
            Log::info('🔍 Google verifyIdToken result', [
                'payload_type' => gettype($payload),
                'payload'      => $payload,
                'audience_cfg' => $clientId,
            ]);

            if ($payload === false || $payload === null || !is_array($payload)) {
                Log::warning('Google token verification failed (returned false)', [
                    'audience' => $clientId,
                    'email'    => $request->email,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Google token. Please try signing in again.',
                ], 401);
            }

            // ----- 3. EXTRACT USER INFO -----
            $googleId = $payload['sub'] ?? null;
            $email    = $payload['email'] ?? null;
            $name     = $payload['name'] ?? $request->name ?? 'Google User';
            $picture  = $payload['picture'] ?? $request->photo;
            $verified = $payload['email_verified'] ?? false;

            if (!$googleId || !$email) {
                Log::warning('Google token missing required claims');
                return response()->json([
                    'success' => false,
                    'message' => 'Google token missing required information',
                ], 401);
            }

            if (!$verified) {
                Log::warning('Google email not verified', ['email' => $email]);
                return response()->json([
                    'success' => false,
                    'message' => 'Google email is not verified',
                ], 401);
            }

            Log::info('Google auth attempt', [
                'email'     => $email,
                'google_id' => $googleId,
            ]);

            // ----- 4. FIND OR CREATE USER -----
            $user = User::where('email', $email)->first();
            $isNewUser = false;

            if (!$user) {
                // ✅ አዲስ ተጠቃሚ → AUTO-REGISTER
                $user = User::create([
                    'name'              => $name,
                    'email'             => $email,
                    'google_id'         => $googleId,
                    'avatar'            => $picture,
                    'password'          => Hash::make(Str::random(32)),
                    'email_verified_at' => $verified ? now() : null,
                    'phone_number'      => null,
                    'role'              => 'user',
                ]);
                $isNewUser = true;

                Log::info('✅ New user registered via Google', [
                    'user_id' => $user->id,
                    'email'   => $email,
                ]);
            } else {
                // ✅ ያለ ተጠቃሚ → UPDATE
                $user->update([
                    'google_id'         => $googleId,
                    'avatar'            => $picture ?? $user->avatar,
                    'email_verified_at' => $verified ? ($user->email_verified_at ?? now()) : $user->email_verified_at,
                ]);

                Log::info('✅ Existing user logged in via Google', [
                    'user_id' => $user->id,
                    'email'   => $email,
                ]);
            }

            // ----- 5. ✅ PROFILE COMPLETE CHECK -----
            // ስም እና password ካሉ profile complete ነው
            // Google ተጠቃሚ ስም ስለሚይዝ profile complete ይሆናል
            $isProfileComplete = !empty($user->name)
                              && strlen($user->name) > 2
                              && !empty($user->password);

            // ----- 6. CREATE TOKEN -----
            $user->tokens()->delete();
            $token = $user->createToken('google_auth_token')->plainTextToken;
            $user->update(['last_login_at' => now()]);

            // ----- 7. ✅ SUCCESS RESPONSE — ቀጥታ HOME -----
            return response()->json([
                'success'     => true,
                'message'     => $isNewUser
                                    ? 'Account created successfully'
                                    : 'Login successful',
                // ✅ ሁልጊዜ 'home' — complete_profile አያስፈልግም
                'next_screen' => 'home',
                'data'        => [
                    'token'               => $token,
                    'user'                => $this->formatUserResponse($user),
                    'role'                => $user->role ?? 'user',
                    'email'               => $user->email,
                    'is_profile_complete' => true,  // ✅ ሁልጊዜ true
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Google login error', [
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
                'email'   => $request->email,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Google authentication failed: ' . $e->getMessage(),
            ], 401);
        }
    }
}