<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class SettingsController extends Controller
{
    /**
     * All settings keys we allow to be stored.
     */
    private array $keys = [
        // General
        'platform_name', 'city', 'admin_email', 'support_phone',
        'currency', 'timezone', 'description',

        // Notifications
        'notify_new_bookings', 'notify_new_users', 'notify_new_venues',
        'notify_payouts', 'notify_emails', 'notify_sms',

        // Security
        'require_2fa', 'force_password_change', 'ip_whitelist',
        'maintenance_mode', 'session_timeout',

        // Payments
        'chapa_enabled', 'chapa_public_key', 'chapa_secret_key',
        'commission_rate', 'min_payout', 'payout_delay_days',

        // Advanced
        'auto_approve_venues', 'allow_registration', 'allow_bookings', 'show_prices',
    ];

    /**
     * GET /api/admin/settings
     */
    public function index()
    {
        try {
            $data = [];
            foreach ($this->keys as $key) {
                $data[$key] = Setting::get($key);
            }

            return response()->json([
                'success' => true,
                'data'    => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Load settings error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load settings.',
            ], 500);
        }
    }

    /**
     * POST /api/admin/settings
     */
    public function update(Request $request)
    {
        try {
            foreach ($this->keys as $key) {
                if ($request->has($key)) {
                    Setting::set($key, $request->input($key));
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Settings saved successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Save settings error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save settings: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/admin/settings/clear-cache
     */
    public function clearCache()
    {
        try {
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');

            return response()->json([
                'success' => true,
                'message' => 'Cache cleared.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Could not clear cache.',
            ], 500);
        }
    }

    /**
     * GET /api/admin/settings/backup
     */
    public function backup()
    {
        $data = [
            'users'    => \App\Models\User::all()->toArray(),
            'venues'   => \App\Models\Venue::all()->toArray(),
            'bookings' => \App\Models\Booking::all()->toArray(),
            'settings' => Setting::all()->toArray(),
            'exported_at' => now()->toIso8601String(),
        ];

        return response()->json($data)
            ->header('Content-Disposition', 'attachment; filename="backup.json"');
    }
}