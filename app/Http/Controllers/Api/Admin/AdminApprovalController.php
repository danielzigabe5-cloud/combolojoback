<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminApprovalController extends Controller
{
    // በመጠባበቅ ላይ ያሉ ሜዳዎችን ማሳየት
    public function pendingVenues()
    {
        try {
            $venues = Venue::where('status', 'pending')
                ->with('user')
                ->orderBy('created_at', 'asc')
                ->get();
                
            Log::info('Pending venues fetched: ' . $venues->count());

            return response()->json([
                'success' => true,
                'data' => $venues
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching pending venues: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch pending venues: ' . $e->getMessage()
            ], 500);
        }
    }

    // ሜዳ ማረጋገጥ
    public function approveVenue($id)
    {
        try {
            $venue = Venue::findOrFail($id);
            
            $admin = auth()->user();
            
            if (!$admin || $admin->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only admins can approve venues.'
                ], 403);
            }
            
            $venue->update([
                'is_active' => true,
                'status' => 'approved',
                'approved_by' => $admin->id,
                'approved_at' => now(),
            ]);
            
            Log::info('Venue approved: ' . $venue->id . ' by admin: ' . $admin->id);

            return response()->json([
                'success' => true,
                'message' => 'Venue approved successfully!',
                'data' => $venue
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error approving venue: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve venue: ' . $e->getMessage()
            ], 500);
        }
    }

    // ሜዳ ውድቅ ማድረግ
    public function rejectVenue($id)
    {
        try {
            $venue = Venue::findOrFail($id);
            
            $admin = auth()->user();
            
            if (!$admin || $admin->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only admins can reject venues.'
                ], 403);
            }
            
            $venue->update([
                'is_active' => false,
                'status' => 'rejected',
            ]);
            
            Log::info('Venue rejected: ' . $venue->id . ' by admin: ' . $admin->id);

            return response()->json([
                'success' => true,
                'message' => 'Venue rejected successfully!',
                'data' => $venue
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error rejecting venue: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject venue: ' . $e->getMessage()
            ], 500);
        }
    }
    // app/Http/Controllers/Api/Admin/AdminApprovalController.php ውስጥ ጨምረው

public function index()
{
    try {
        // ሁሉንም ሜዳዎች ከባለቤታቸው (user) ጋር ያመጣል
        $venues = Venue::with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $venues
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ], 500);
    }
}
}