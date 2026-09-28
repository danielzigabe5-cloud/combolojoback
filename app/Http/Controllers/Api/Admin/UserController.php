<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    /**
     * GET /api/admin/users
     */
   public function index(Request $request)
{
    try {
        $query = User::query();

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'LIKE', "%{$s}%")
                  ->orWhere('email', 'LIKE', "%{$s}%")
                  ->orWhere('phone_number', 'LIKE', "%{$s}%");   // ✅ correct column
            });
        }

        $users = $query->orderBy('created_at', 'desc')->get()->map(function ($u) {
            // 🆕 Build the display phone number
            $rawPhone = $u->phone_number;                    // ✅ the real column
            $display  = $rawPhone;

            if ($rawPhone && $u->phone_country_code && !str_starts_with($rawPhone, '+')) {
                $clean   = ltrim($rawPhone, '0');
                $display = $u->phone_country_code . ' ' . $clean;
            }

            return [
                'id'                 => $u->id,
                'name'               => $u->name,
                'email'              => $u->email,

                // 🆕 Send under every common name so the frontend never misses it
                'phone'              => $rawPhone,       // short alias
                'phone_number'       => $rawPhone,       // DB column name
                'phone_full'         => $display,        // with country code
                'phoneNumber'        => $rawPhone,       // camelCase

                'phone_country_code' => $u->phone_country_code,
                'phone_country_iso'  => $u->phone_country_iso,

                'role'               => $u->role,
                'status'             => $u->status ?? 'active',
                'avatar'             => $u->avatar,
                'is_active'          => $u->is_active ?? true,
                'city'               => $u->city ?? null,
                'created_at'         => $u->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $users,
        ]);
    } catch (\Exception $e) {
        Log::error('Admin users index error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to load users: ' . $e->getMessage(),
        ], 500);
    }
}
    /**
     * GET /api/admin/users/{id}
     */
   public function show($id)
{
    try {
        $u = User::withCount('venues')->findOrFail($id);

        // Calculate total earnings (for partners)
        $venueIds = \App\Models\Venue::where('owner_id', $u->id)
            ->orWhere('user_id', $u->id)
            ->pluck('id');

        $earnings = \App\Models\Booking::whereIn('venue_id', $venueIds)
            ->whereIn('status', ['completed', 'Completed'])
            ->sum('total_price');

        $bookingsCount = \App\Models\Booking::whereIn('venue_id', $venueIds)->count();

        // Build display phone
        $rawPhone = $u->phone_number;
        $display = $rawPhone;
        if ($rawPhone && $u->phone_country_code && !str_starts_with($rawPhone, '+')) {
            $clean = ltrim($rawPhone, '0');
            $display = $u->phone_country_code . ' ' . $clean;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id'                  => $u->id,
                'name'                => $u->name,
                'email'               => $u->email,

                // Phone under multiple names
                'phone'               => $rawPhone,
                'phone_number'        => $rawPhone,
                'phone_full'          => $display,
                'phone_country_code'  => $u->phone_country_code,
                'phone_country_iso'   => $u->phone_country_iso,

                'role'                => $u->role,
                'status'              => $u->status ?? 'active',
                'avatar'              => $u->avatar,
                'city'                => $u->city,
                'is_active'           => $u->is_active ?? true,

                'email_verified_at'   => $u->email_verified_at,
                'phone_verified_at'   => $u->phone_verified_at,
                'created_at'          => $u->created_at,
                'updated_at'          => $u->updated_at,

                // Partner fields
                'bank_name'           => $u->bank_name,
                'bank_account_number' => $u->bank_account_number,
                'bank_account_name'   => $u->bank_account_name,

                // Stats
                'venues_count'        => $u->venues_count ?? 0,
                'bookings_count'      => $bookingsCount,
                'total_earnings'      => (float) $earnings,
            ],
        ]);
    } catch (\Exception $e) {
        Log::error('Admin user show error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'User not found: ' . $e->getMessage(),
        ], 404);
    }
}

    /**
     * PATCH /api/admin/users/{id}/role
     */
    public function updateRole(Request $request, $id)
    {
        $request->validate([
            'role' => 'required|in:admin,partner,owner,user',
        ]);

        $user = User::findOrFail($id);
        $user->role = $request->role;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'User role updated.',
            'data'    => $user,
        ]);
    }

    /**
     * PATCH /api/admin/users/{id}/status
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:active,pending,blocked',
        ]);

        $user = User::findOrFail($id);
        $user->status = $request->status;
        $user->is_active = $request->status === 'active';
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'User status updated.',
            'data'    => $user,
        ]);
    }
}