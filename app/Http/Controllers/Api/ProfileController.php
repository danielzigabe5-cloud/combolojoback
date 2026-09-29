<?php

namespace App\Http\Controllers\Api;    // ✅ ተስተካክሏል

use App\Http\Controllers\Controller;   // ✅ Base Controller
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    /**
     * GET /api/auth/profile
     */
    public function show(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'user'    => $this->formatUser($user),
        ]);
    }

    /**
     * PUT/POST /api/auth/profile
     */
    public function update(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'name'             => 'sometimes|required|string|max:255',
            'email'            => 'sometimes|required|email|max:255|unique:users,email,' . $user->id,
            'phone_number'     => 'nullable|string|max:20',
            'avatar'           => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'current_password' => 'nullable|required_with:password|string',
            'password'         => [
                'nullable',
                'confirmed',
                Password::min(6)->mixedCase()->numbers(),
            ],
        ], [
            'name.required'         => 'Full name is required.',
            'email.required'        => 'Email is required.',
            'email.unique'          => 'This email is already taken.',
            'avatar.image'          => 'Avatar must be a valid image.',
            'avatar.max'            => 'Avatar must be smaller than 2MB.',
            'password.confirmed'    => 'Password confirmation does not match.',
            'password.min'          => 'Password must be at least 6 characters.',
            'current_password.required_with' => 'Current password is required to change password.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Password change
        if ($request->filled('password')) {
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect.',
                    'errors'  => [
                        'current_password' => ['Current password is incorrect.'],
                    ],
                ], 422);
            }
            $user->password = Hash::make($request->password);
        }

        // Avatar upload
        if ($request->hasFile('avatar')) {
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = $path;
        }

        // Update fields
        if ($request->filled('name')) {
            $user->name = $request->name;
        }
        if ($request->filled('email')) {
            $user->email = $request->email;
        }
        if ($request->has('phone_number')) {
            $user->phone_number = $request->phone_number;
        }

        $user->save();
        $user->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'user'    => $this->formatUser($user),
        ]);
    }

    /**
     * DELETE /api/auth/profile/avatar
     */
    public function removeAvatar(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->avatar = null;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Avatar removed successfully.',
            'user'    => $this->formatUser($user->fresh()),
        ]);
    }

    private function formatUser($user): array
    {
        return [
            'id'           => $user->id,
            'name'         => $user->name,
            'email'        => $user->email,
            'phone_number' => $user->phone_number ?? null,
            'role'         => $user->role ?? 'user',
            'avatar'       => $user->avatar ?? null,
            'avatar_url'   => $user->avatar
                ? asset('storage/' . $user->avatar)
                : null,
            'created_at'   => $user->created_at,
            'updated_at'   => $user->updated_at,
        ];
    }
}