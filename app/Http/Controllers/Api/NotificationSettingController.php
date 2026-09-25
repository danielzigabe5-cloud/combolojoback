<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class NotificationSettingController extends Controller
{
    /**
     * ═══════════════════════════════════════════════════════
     * GET /api/notification-settings
     * የተጠቃሚውን settings አምጣ
     * ═══════════════════════════════════════════════════════
     */
    public function show()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $settings = NotificationSetting::forUser($user->id);

        return response()->json([
            'success' => true,
            'settings' => $settings,
        ]);
    }

    /**
     * ═══════════════════════════════════════════════════════
     * POST /api/notification-settings
     * Settings አዘምን (update)
     * ═══════════════════════════════════════════════════════
     */
    public function update(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $validator = Validator::make($request->all(), [
            'push_enabled'        => 'boolean',
            'booking_updates'     => 'boolean',
            'reminders'           => 'boolean',
            'payment_updates'     => 'boolean',
            'reviews'             => 'boolean',
            'offers'              => 'boolean',
            'news'                => 'boolean',
            'quiet_hours_enabled' => 'boolean',
            'quiet_hours_start'   => 'nullable|date_format:H:i',
            'quiet_hours_end'     => 'nullable|date_format:H:i',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $settings = NotificationSetting::forUser($user->id);

            // ✅ የተላኩትን fields ብቻ አዘምን
            $settings->fill($request->only([
                'push_enabled',
                'booking_updates',
                'reminders',
                'payment_updates',
                'reviews',
                'offers',
                'news',
                'quiet_hours_enabled',
                'quiet_hours_start',
                'quiet_hours_end',
            ]));

            // ✅ Push off ከሆነ ሁሉንም types off አድርግ
            if ($request->has('push_enabled') && !$request->push_enabled) {
                $settings->booking_updates = false;
                $settings->reminders = false;
                $settings->payment_updates = false;
                $settings->reviews = false;
                $settings->offers = false;
                $settings->news = false;
            }

            $settings->save();

            return response()->json([
                'success'  => true,
                'message'  => 'Notification settings updated ✅',
                'settings' => $settings->fresh(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ═══════════════════════════════════════════════════════
     * POST /api/notification-settings/reset
     * Settings ወደ default መልስ
     * ═══════════════════════════════════════════════════════
     */
    public function reset()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $settings = NotificationSetting::forUser($user->id);
        $settings->update([
            'push_enabled'        => true,
            'booking_updates'     => true,
            'reminders'           => true,
            'payment_updates'     => true,
            'reviews'             => true,
            'offers'              => false,
            'news'                => false,
            'quiet_hours_enabled' => false,
        ]);

        return response()->json([
            'success'  => true,
            'message'  => 'Settings reset to default',
            'settings' => $settings->fresh(),
        ]);
    }

    /**
     * ═══════════════════════════════════════════════════════
     * POST /api/notification-settings/test
     * የ test notification ላክ (debug ለማድረግ)
     * ═══════════════════════════════════════════════════════
     */
    public function test()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $settings = NotificationSetting::forUser($user->id);

        if (!$settings->canSendNow()) {
            return response()->json([
                'success' => false,
                'message' => 'You are in quiet hours. Test skipped.',
            ]);
        }

        // ✅ እዚህ እውነተኛ push notification መላክ ትችላለህ
        // Firebase / OneSignal / etc.

        return response()->json([
            'success' => true,
            'message' => 'Test notification sent ✅',
        ]);
    }
}