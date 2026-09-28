<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        try {
            // ── Date Range ──
            $end   = $request->filled('end')
                ? Carbon::parse($request->end)->endOfDay()
                : Carbon::now();
            $start = $request->filled('start')
                ? Carbon::parse($request->start)->startOfDay()
                : Carbon::now()->subMonth();

            // ── Previous period for % change ──
            $periodDays = $start->diffInDays($end) ?: 30;
            $prevEnd    = $start->copy()->subDay()->endOfDay();
            $prevStart  = $prevEnd->copy()->subDays($periodDays)->startOfDay();

            // ── Core totals ──
            $totalBookings = Booking::whereBetween('created_at', [$start, $end])->count();
            $totalRevenue  = Booking::whereBetween('created_at', [$start, $end])
                ->whereIn('status', ['confirmed', 'completed', 'Confirmed', 'Completed'])
                ->sum('total_price');
            $totalVenues   = Venue::whereBetween('created_at', [$start, $end])->count();
            $totalUsers    = User::whereBetween('created_at', [$start, $end])->count();

            // ── Previous period totals ──
            $prevBookings = Booking::whereBetween('created_at', [$prevStart, $prevEnd])->count();
            $prevRevenue  = Booking::whereBetween('created_at', [$prevStart, $prevEnd])
                ->whereIn('status', ['confirmed', 'completed', 'Confirmed', 'Completed'])
                ->sum('total_price');
            $prevVenues   = Venue::whereBetween('created_at', [$prevStart, $prevEnd])->count();
            $prevUsers    = User::whereBetween('created_at', [$prevStart, $prevEnd])->count();

            $pct = fn ($curr, $prev) => $prev > 0
                ? round((($curr - $prev) / $prev) * 100, 1)
                : 0;

            // ── Monthly chart data ──
            $monthlyRaw = Booking::select(
                    DB::raw('YEAR(created_at) as year'),
                    DB::raw('MONTH(created_at) as month_num'),
                    DB::raw('COUNT(*) as bookings'),
                    DB::raw('SUM(CASE WHEN status IN ("confirmed","completed","Confirmed","Completed") THEN total_price ELSE 0 END) as revenue')
                )
                ->whereBetween('created_at', [$start, $end])
                ->groupBy('year', 'month_num')
                ->orderBy('year')->orderBy('month_num')
                ->get();

            $monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            $monthly = $monthlyRaw->map(function ($row) use ($monthNames) {
                return [
                    'month'    => $monthNames[$row->month_num - 1],
                    'bookings' => (int) $row->bookings,
                    'revenue'  => (float) $row->revenue,
                ];
            })->values();

            // ── Top venues ──
            $topVenues = Venue::select('venues.id', 'venues.name', 'venues.city')
                ->leftJoin('bookings', 'bookings.venue_id', '=', 'venues.id')
                ->whereBetween('bookings.created_at', [$start, $end])
                ->selectRaw('COUNT(bookings.id) as bookings')
                ->selectRaw('COALESCE(SUM(CASE WHEN bookings.status IN ("confirmed","completed","Confirmed","Completed") THEN bookings.total_price ELSE 0 END), 0) as revenue')
                ->groupBy('venues.id', 'venues.name', 'venues.city')
                ->orderByDesc('revenue')
                ->limit(5)
                ->get()
                ->map(fn ($v) => [
                    'id'       => $v->id,
                    'name'     => $v->name,
                    'city'     => $v->city,
                    'bookings' => (int) $v->bookings,
                    'revenue'  => (float) $v->revenue,
                ]);

            // ── Top sports (via venues.sport_types if array column) ──
            $topSports = Booking::select('venues.sport_types')
                ->leftJoin('venues', 'bookings.venue_id', '=', 'venues.id')
                ->whereBetween('bookings.created_at', [$start, $end])
                ->get()
                ->flatMap(function ($b) {
                    $types = $b->sport_types;
                    if (is_string($types)) $types = json_decode($types, true) ?? [$types];
                    return is_array($types) ? $types : [];
                })
                ->filter()
                ->countBy()
                ->sortDesc()
                ->take(5)
                ->map(fn ($count, $name) => ['name' => $name, 'count' => $count])
                ->values();

            // ── Top partners ──
            $topPartners = User::whereIn('role', ['partner', 'owner'])
                ->withCount('venues')
                ->withSum(['payouts as earnings' => function ($q) {
                    $q->where('status', 'paid');
                }], 'amount')
                ->orderByDesc('earnings')
                ->limit(5)
                ->get()
                ->map(fn ($u) => [
                    'id'       => $u->id,
                    'name'     => $u->name,
                    'venues'   => $u->venues_count,
                    'earnings' => (float) ($u->earnings ?? 0),
                ]);

            // ── Top cities ──
            $topCities = Booking::select('venues.city')
                ->leftJoin('venues', 'bookings.venue_id', '=', 'venues.id')
                ->whereBetween('bookings.created_at', [$start, $end])
                ->whereNotNull('venues.city')
                ->groupBy('venues.city')
                ->selectRaw('COUNT(*) as count')
                ->orderByDesc('count')
                ->limit(5)
                ->get()
                ->map(fn ($c) => ['name' => $c->city, 'count' => (int) $c->count]);

            // ── Summary rows ──
            $summaryRows = Venue::select('venues.id', 'venues.name', 'venues.city')
                ->leftJoin('bookings', 'bookings.venue_id', '=', 'venues.id')
                ->whereBetween('bookings.created_at', [$start, $end])
                ->selectRaw('COUNT(bookings.id) as bookings')
                ->selectRaw('COALESCE(SUM(CASE WHEN bookings.status IN ("confirmed","completed","Confirmed","Completed") THEN bookings.total_price ELSE 0 END), 0) as revenue')
                ->groupBy('venues.id', 'venues.name', 'venues.city')
                ->orderByDesc('bookings')
                ->limit(50)
                ->get()
                ->map(fn ($v) => [
                    'id'       => $v->id,
                    'name'     => $v->name,
                    'city'     => $v->city,
                    'bookings' => (int) $v->bookings,
                    'revenue'  => (float) $v->revenue,
                ]);

            // ── Completion rate ──
            $total     = Booking::whereBetween('created_at', [$start, $end])->count();
            $completed = Booking::whereBetween('created_at', [$start, $end])
                ->whereIn('status', ['completed', 'Completed'])
                ->count();
            $completionRate = $total > 0 ? round(($completed / $total) * 100, 1) : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'total_bookings'  => $totalBookings,
                    'total_revenue'   => (float) $totalRevenue,
                    'total_venues'    => $totalVenues,
                    'total_users'     => $totalUsers,
                    'bookings_change' => $pct($totalBookings, $prevBookings),
                    'revenue_change'  => $pct((float) $totalRevenue, (float) $prevRevenue),
                    'venues_change'   => $pct($totalVenues, $prevVenues),
                    'users_change'    => $pct($totalUsers, $prevUsers),
                    'completion_rate' => $completionRate,
                    'monthly'         => $monthly,
                    'top_venues'      => $topVenues,
                    'top_sports'      => $topSports,
                    'top_partners'    => $topPartners,
                    'top_cities'      => $topCities,
                    'summary_rows'    => $summaryRows,
                    'range'           => [
                        'start' => $start->toDateString(),
                        'end'   => $end->toDateString(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Reports error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load reports: ' . $e->getMessage(),
            ], 500);
        }
    }
}