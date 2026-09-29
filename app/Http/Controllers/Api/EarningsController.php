<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payout;
use App\Models\Venue;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EarningsController extends Controller
{
    /**
     * 🎯 የአጋሩ ጠቅላላ የገቢ ዳሽቦርድ
     *
     * 🎯 FIX: ከዚህ በፊት wallet_balance በሂሳብ (formula) ይሰላ ነበር:
     *     $walletBalance = $totalEarnings - $totalPaidOut - $pendingPayouts;
     *
     *     ይህ ከ PayoutController ጋር አይመሳሰልም ነበር (ያ PayoutController
     *     Wallet table ን ብቻ ያነባል)። ስለዚህ Frontend "ETB 1,200" ያሳይ ነበር
     *     ግን Withdraw ሲደረግ "Insufficient balance. Available: ETB 0" ይል ነበር።
     *
     *     አሁን ከ Wallet table ብቻ ያነባል — ሁለቱም ስርዓቶች ይመሳሰላሉ።
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized.',
                ], 401);
            }

            // ── 🎯 Wallet (single source of truth) ──
            $wallet = Wallet::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'available_balance' => 0,
                    'pending_balance'   => 0,
                    'total_earned'      => 0,
                    'total_withdrawn'   => 0,
                ]
            );

            // ── የአጋሩን ሜዳዎች ──
            $venueIds = Venue::where('owner_id', $user->id)
                ->orWhere('user_id', $user->id)
                ->pluck('id');

            if ($venueIds->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data'    => $this->emptyData($wallet),
                ]);
            }

            // 🎯 1. ጠቅላላ ገቢ (confirmed bookings) — ለስታቲስቲክስ ብቻ
            $totalEarnings = Booking::whereIn('venue_id', $venueIds)
                ->where('status', 'confirmed')
                ->sum('total_price');

            // 🎯 2. የዚህ ወር ገቢ
            $monthStart = Carbon::now()->startOfMonth();
            $monthEarnings = Booking::whereIn('venue_id', $venueIds)
                ->where('status', 'confirmed')
                ->where('created_at', '>=', $monthStart)
                ->sum('total_price');

            // 🎯 3. ያለፈው ወር ገቢ (ለ % ስሌት)
            $lastMonthStart = Carbon::now()->subMonth()->startOfMonth();
            $lastMonthEnd   = Carbon::now()->subMonth()->endOfMonth();
            $lastMonthEarnings = Booking::whereIn('venue_id', $venueIds)
                ->where('status', 'confirmed')
                ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])
                ->sum('total_price');

            // 🎯 4. በመጠባበቅ ላይ ያለ ገቢ (pending bookings)
            $pendingEarnings = Booking::whereIn('venue_id', $venueIds)
                ->where('status', 'pending')
                ->sum('total_price');

            // 🎯 5-7. ከ Wallet table ያንብቡ (ስሌት አያስፈልግም)
            //    እነዚህ እሴቶች በ PayoutController ይሻሻላሉ፣ ስለዚህ እዚህ ብቻ ያንብቡ።
            $walletBalance  = (float) $wallet->available_balance;
            $pendingPayouts = (float) $wallet->pending_balance;
            $totalPaidOut   = (float) $wallet->total_withdrawn;

            // 🎯 8. የወር ጭማሪ መጠን
            $monthChange = $lastMonthEarnings > 0
                ? round((($monthEarnings - $lastMonthEarnings) / $lastMonthEarnings) * 100, 1)
                : 0;

            // 🎯 9. የ6 ወር chart
            $chart = $this->getMonthlyChart($venueIds);

            // 🎯 10. የቅርብ ጊዜ transactions
            $transactions = $this->getRecentTransactions($venueIds);

            return response()->json([
                'success' => true,
                'data'    => [
                    'stats' => [
                        [
                            'title'  => 'Total Earnings',
                            'value'  => 'ETB ' . number_format($totalEarnings),
                            'change' => '+' . ($monthChange > 0 ? $monthChange : 0) . '%',
                            'icon'   => '💰',
                            'trend'  => 'up',
                        ],
                        [
                            'title'  => 'This Month',
                            'value'  => 'ETB ' . number_format($monthEarnings),
                            'change' => ($monthChange >= 0 ? '+' : '') . $monthChange . '%',
                            'icon'   => '📈',
                            'trend'  => $monthChange >= 0 ? 'up' : 'down',
                        ],
                        [
                            'title'  => 'Available Balance',
                            // 🎯 FIX: ከ Wallet table (ከ PayoutController ጋር ይመሳሰላል)
                            'value'  => 'ETB ' . number_format($walletBalance),
                            'change' => $walletBalance >= 100 ? 'Ready to withdraw' : 'Below minimum',
                            'icon'   => '💳',
                            'trend'  => 'neutral',
                        ],
                        [
                            'title'  => 'Pending',
                            'value'  => 'ETB ' . number_format($pendingEarnings + $pendingPayouts),
                            'change' => 'Processing',
                            'icon'   => '⏳',
                            'trend'  => 'neutral',
                        ],
                    ],
                    'chart'        => $chart,
                    'transactions' => $transactions,

                    // 🎯 FIX: ከ Wallet table (ከ PayoutController ጋር ይመሳሰላል)
                    'wallet_balance'  => $walletBalance,
                    'pending_balance' => $pendingEarnings + $pendingPayouts,

                    // ለስታቲስቲክስ ብቻ
                    'total_earnings' => (float) $totalEarnings,
                    'total_paid_out' => $totalPaidOut,
                    'month_earnings' => (float) $monthEarnings,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Earnings error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to load earnings: ' . $e->getMessage(),
            ], 500);
        }
    }

    /* ═══════════════════════════════════════════
       PRIVATE HELPERS
       ═══════════════════════════════════════════ */

    /**
     * 🎯 FIX: Wallet ን ይቀበላል ስለዚህ ትክክለኛውን balance ያሳያል
     */
    private function emptyData(?Wallet $wallet = null): array
    {
        $walletBalance  = $wallet ? (float) $wallet->available_balance : 0;
        $pendingBalance = $wallet ? (float) $wallet->pending_balance : 0;

        return [
            'stats' => [
                [
                    'title'  => 'Total Earnings',
                    'value'  => 'ETB 0',
                    'change' => '+0%',
                    'icon'   => '💰',
                    'trend'  => 'up',
                ],
                [
                    'title'  => 'This Month',
                    'value'  => 'ETB 0',
                    'change' => '+0%',
                    'icon'   => '📈',
                    'trend'  => 'up',
                ],
                [
                    'title'  => 'Available Balance',
                    'value'  => 'ETB ' . number_format($walletBalance),
                    'change' => $walletBalance >= 100 ? 'Ready to withdraw' : 'Below minimum',
                    'icon'   => '💳',
                    'trend'  => 'neutral',
                ],
                [
                    'title'  => 'Pending',
                    'value'  => 'ETB ' . number_format($pendingBalance),
                    'change' => 'Processing',
                    'icon'   => '⏳',
                    'trend'  => 'neutral',
                ],
            ],
            'chart'           => [],
            'transactions'    => [],
            'wallet_balance'  => $walletBalance,
            'pending_balance' => $pendingBalance,
            'total_earnings'  => 0,
            'total_paid_out'  => 0,
            'month_earnings'  => 0,
        ];
    }

    /**
     * የ6 ወር ገቢ ግራፍ
     */
    private function getMonthlyChart($venueIds): array
    {
        $chart = [];
        $maxAmount = 1;

        // የ6 ወር ዳታ
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);

            $amount = Booking::whereIn('venue_id', $venueIds)
                ->where('status', 'confirmed')
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->sum('total_price');

            $chart[] = [
                'month'  => $date->format('M'),
                'amount' => number_format($amount),
                'raw'    => (float) $amount,
            ];

            if ($amount > $maxAmount) $maxAmount = $amount;
        }

        // Height ስሌት (0-100%)
        return array_map(function ($item) use ($maxAmount) {
            $item['height'] = $maxAmount > 0
                ? max(5, round(($item['raw'] / $maxAmount) * 100))
                : 0;
            unset($item['raw']);
            return $item;
        }, $chart);
    }

    /**
     * የቅርብ ጊዜ ገቢዎች
     */
    private function getRecentTransactions($venueIds, int $limit = 5): array
    {
        return Booking::with(['user:id,name'])
            ->whereIn('venue_id', $venueIds)
            ->where('status', 'confirmed')
            ->latest()
            ->take($limit)
            ->get()
            ->map(function ($booking) {
                return [
                    'id'          => $booking->id,
                    'description' => 'Booking #BK-' . str_pad($booking->id, 4, '0', STR_PAD_LEFT),
                    'date'        => $booking->created_at?->diffForHumans() ?? '—',
                    'amount'      => number_format($booking->total_price ?? 0),
                    'type'        => 'income',
                ];
            })
            ->toArray();
    }
}