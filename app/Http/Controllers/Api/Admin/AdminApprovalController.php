<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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


    // =========================================================
    // ሜዳ ማረጋገጥ + Owner => Partner
    // =========================================================
    public function approveVenue(Request $request, $id)
    {
        try {

            // =====================================================
            // 1. Get logged-in admin
            // =====================================================

            $admin = auth()->user();

            if (!$admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.'
                ], 401);
            }

            if ($admin->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only admins can approve venues.'
                ], 403);
            }


            // =====================================================
            // 2. Find venue
            // =====================================================

            $venue = Venue::findOrFail($id);


            // =====================================================
            // 3. Find owner ID
            // =====================================================

            // owner_id ካለ እሱን እንጠቀማለን
            // owner_id ከሌለ user_id እንጠቀማለን
            $ownerId = $venue->owner_id ?? $venue->user_id;

            if (!$ownerId) {

                Log::error('VENUE OWNER ID NOT FOUND', [
                    'venue_id' => $venue->id,
                    'venue_name' => $venue->name,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'This venue has no owner.',
                    'venue_id' => $venue->id
                ], 400);
            }


            // =====================================================
            // 4. Find owner user
            // =====================================================

            $owner = User::find($ownerId);

            if (!$owner) {

                Log::error('VENUE OWNER USER NOT FOUND', [
                    'venue_id' => $venue->id,
                    'owner_id' => $ownerId,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Venue owner not found.',
                    'owner_id' => $ownerId
                ], 404);
            }


            // =====================================================
            // 5. Update Venue + Owner Role
            // =====================================================

            DB::transaction(function () use ($venue, $owner, $admin) {

                // -------------------------------
                // Venue => APPROVED
                // -------------------------------

                $venue->is_active = true;
                $venue->status = 'approved';
                $venue->approved_by = $admin->id;
                $venue->approved_at = now();

                $venue->save();


                // -------------------------------
                // Owner User => PARTNER
                // -------------------------------

                $owner->role = 'partner';
                $owner->save();
            });


            // =====================================================
            // 6. Refresh from database
            // =====================================================

            $venue->refresh();
            $owner->refresh();


            // =====================================================
            // 7. Log result
            // =====================================================

            Log::info('========================================');
            Log::info('VENUE APPROVAL SUCCESS');
            Log::info('========================================');

            Log::info('Venue ID: ' . $venue->id);
            Log::info('Venue Name: ' . $venue->name);
            Log::info('Venue Status: ' . $venue->status);
            Log::info('Venue Active: ' . $venue->is_active);

            Log::info('Owner ID: ' . $owner->id);
            Log::info('Owner Email: ' . $owner->email);
            Log::info('Owner Role: ' . $owner->role);

            Log::info('Approved By Admin ID: ' . $admin->id);

            Log::info('========================================');


            // =====================================================
            // 8. Return response
            // =====================================================

            return response()->json([
                'success' => true,

                'message' => 'Venue approved successfully and owner promoted to partner!',

                'data' => [
                    'venue' => $venue,

                    'owner' => [
                        'id' => $owner->id,
                        'name' => $owner->name,
                        'email' => $owner->email,
                        'role' => $owner->role,
                    ],

                    'approved_by' => $admin->id
                ]
            ], 200);


        } catch (\Exception $e) {

            Log::error('========================================');
            Log::error('VENUE APPROVAL ERROR');
            Log::error('========================================');

            Log::error('Venue ID: ' . $id);
            Log::error('Error: ' . $e->getMessage());
            Log::error('File: ' . $e->getFile());
            Log::error('Line: ' . $e->getLine());

            Log::error('========================================');

            return response()->json([
                'success' => false,
                'message' => 'Failed to approve venue: ' . $e->getMessage()
            ], 500);
        }
    }


    // =========================================================
    // ሜዳ ውድቅ ማድረግ
    // =========================================================
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

            Log::info(
                'Venue rejected: ' .
                $venue->id .
                ' by admin: ' .
                $admin->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Venue rejected successfully!',
                'data' => $venue
            ]);

        } catch (\Exception $e) {

            Log::error(
                'Error rejecting venue: ' .
                $e->getMessage()
            );

            return response()->json([
                'success' => false,
                'message' => 'Failed to reject venue: ' . $e->getMessage()
            ], 500);
        }
    }


    // =========================================================
    // ሁሉንም ሜዳዎች ማሳየት
    // =========================================================
    public function index()
    {
        try {

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

