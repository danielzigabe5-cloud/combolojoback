<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdminProfileController extends Controller
{
    public function update(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated user'], 401);
        }

        // Validation Rules
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:6',
            'avatar'   => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        // Update basic info
        $user->name = $request->name;
        $user->email = $request->email;

        // Password Update (if filled)
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        // Photo Upload Handling
        if ($request->hasFile('avatar')) {
            if ($user->avatar && !filter_var($user->avatar, FILTER_VALIDATE_URL)) {
                $oldPath = str_replace('/storage/', '', $user->avatar);
                Storage::disk('public')->delete($oldPath);
            }

            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = Storage::url($path);
        }

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'user'    => $user
        ], 200);
    }
}