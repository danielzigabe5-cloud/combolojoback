<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AdminProfileController extends Controller
{
    /**
     * Update the authenticated user's profile.
     * POST /api/admin/profile
     */
    public function update(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            // ── Validation ──
            $validator = Validator::make($request->all(), [
                'name'                  => 'required|string|min:2|max:255',
                'email'                 => 'required|email|max:255|unique:users,email,' . $user->id,
                'phone_number'          => 'nullable|string|max:30',
                'avatar'                => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',

                // Password change (optional)
                'current_password'      => 'required_with:password|string',
                'password'              => 'nullable|string|min:8|confirmed',
                'password_confirmation' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                    'errors'  => $validator->errors(),
                ], 422);
            }

            // ── Update basic fields ──
            $user->name = $request->name;
            $user->email = $request->email;

            if ($request->filled('phone_number')) {
                $user->phone_number = $request->phone_number;
            }

            // ── Password change ──
            if ($request->filled('password')) {
                if (!Hash::check($request->current_password, $user->password)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Current password is incorrect.',
                        'errors'  => ['current_password' => ['Current password is incorrect.']],
                    ], 422);
                }

                $user->password = Hash::make($request->password);
            }

            // ── Avatar upload ──
            if ($request->hasFile('avatar')) {
                // Delete old avatar
                if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                    Storage::disk('public')->delete($user->avatar);
                }

                // Store new
                $path = $request->file('avatar')->store('avatars', 'public');
                $user->avatar = $path;
            }

            $user->save();

            // ── Fresh data with image URL ──
            $fresh = $user->fresh();
            if ($fresh->avatar) {
                $fresh->avatar_url = asset('storage/' . $fresh->avatar);
            }

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully.',
                'user'    => [
                    'id'            => $fresh->id,
                    'name'          => $fresh->name,
                    'email'         => $fresh->email,
                    'phone_number'  => $fresh->phone_number,
                    'role'          => $fresh->role,
                    'avatar'        => $fresh->avatar_url ?? null,
                    'created_at'    => $fresh->created_at,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Admin profile update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile: ' . $e->getMessage(),
            ], 500);
        }
    }
}