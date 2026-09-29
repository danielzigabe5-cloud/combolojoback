<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payout;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayoutController extends Controller
{
    /* ═══════════════════════════════════════════════
       GET /api/admin/wallet
       Platform-wide wallet summary
       ═══════════════════════════════════════════════ */
    public function wallet(Request $request)
    {
        try {
            $availableBalance = Wallet::sum('available_balance');

            $pendingPayouts = Payout::where('status', 'pending')->sum('amount');

            $totalPaidOut = Payout::where('status', 'paid')->sum('amount');

            return response()->json([
                'success' => true,
                'data' => [
                    'available_balance' => (float) $availableBalance,
                    'pending_payouts'   => (float) $pendingPayouts,
                    'total_paid_out'    => (float) $totalPaidOut,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Wallet summary error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load wallet data.',
            ], 500);
        }
    }

    /* ═══════════════════════════════════════════════
       GET /api/admin/payouts
       List all payout requests
       ═══════════════════════════════════════════════ */
    public function index(Request $request)
{
    try {
        $payouts = Payout::with('user:id,name,email')
            ->orderByRaw("FIELD(status, 'pending', 'paid', 'rejected')")
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($p) {
                return [
                    'id'             => $p->id,
                    'partner_name'   => $p->user->name ?? 'Unknown',
                    'partner_email'  => $p->user->email ?? null,
                    'amount'         => (float) $p->amount,
                    'method'         => $p->method,
                    'account_number' => $p->account_number,
                    'bank_name'      => $p->bank_name,
                    'status'         => $p->status,
                    'created_at'     => $p->created_at,
                ];
            });

        $transactions = Transaction::with('user:id,name')
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get()
            ->map(function ($t) {
                return [
                    'id'          => $t->id,
                    'description' => $t->description,
                    'type'        => $t->type,
                    'amount'      => (float) $t->amount,
                    'reference'   => $t->reference,
                    'created_at'  => $t->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'payouts'      => $payouts,
                'transactions' => $transactions,
            ],
        ]);
    } catch (\Exception $e) {
        Log::error('Admin payouts index error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to load payouts.',
        ], 500);
    }
}
    /* ═══════════════════════════════════════════════
       PATCH /api/admin/payouts/{id}/approve
       Approve a payout (mark as paid)
       ═══════════════════════════════════════════════ */
    public function approve(Request $request, $id)
    {
        return $this->process($request, $id, 'paid');
    }

    /* ═══════════════════════════════════════════════
       PATCH /api/admin/payouts/{id}/reject
       Reject a payout
       ═══════════════════════════════════════════════ */
    public function reject(Request $request, $id)
    {
        return $this->process($request, $id, 'rejected');
    }

    /* ═══════════════════════════════════════════════
       Shared process logic
       ═══════════════════════════════════════════════ */
    private function process(Request $request, $id, string $newStatus)
    {
        try {
            $payout = Payout::with('user')->findOrFail($id);

            if ($payout->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'This payout has already been processed.',
                ], 400);
            }

            DB::beginTransaction();

            $wallet = Wallet::firstOrCreate(
                ['user_id' => $payout->user_id],
                [
                    'available_balance' => 0,
                    'pending_balance'   => 0,
                    'total_earned'      => 0,
                    'total_withdrawn'   => 0,
                ]
            );

            if ($newStatus === 'paid') {
                // Move from pending → withdrawn
                $wallet->pending_balance = max(0, (float) $wallet->pending_balance - (float) $payout->amount);
                $wallet->total_withdrawn += (float) $payout->amount;

                // Log transaction
                Transaction::create([
                    'user_id'       => $payout->user_id,
                    'description'   => "Payout #{$payout->id} approved",
                    'type'          => 'debit',
                    'amount'        => $payout->amount,
                    'balance_after' => $wallet->available_balance,
                    'reference'     => "PAYOUT-{$payout->id}",
                    'category'      => 'payout',
                    'related_id'    => $payout->id,
                ]);
            } else {
                // Rejected → refund to available balance
                $wallet->pending_balance   = max(0, (float) $wallet->pending_balance   - (float) $payout->amount);
                $wallet->available_balance += (float) $payout->amount;

                Transaction::create([
                    'user_id'       => $payout->user_id,
                    'description'   => "Payout #{$payout->id} rejected — refunded",
                    'type'          => 'credit',
                    'amount'        => $payout->amount,
                    'balance_after' => $wallet->available_balance,
                    'reference'     => "PAYOUT-{$payout->id}",
                    'category'      => 'adjustment',
                    'related_id'    => $payout->id,
                ]);
            }

            $wallet->save();

            $payout->status       = $newStatus;
            $payout->processed_at = now();
            $payout->processed_by = $request->user()?->id;
            $payout->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $newStatus === 'paid'
                    ? 'Payout approved successfully.'
                    : 'Payout rejected and funds returned to partner.',
                'data'    => $payout->fresh(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Process payout error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to process payout: ' . $e->getMessage(),
            ], 500);
        }
    }
}