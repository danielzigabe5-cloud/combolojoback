<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Payout;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PayoutController extends Controller
{
    /* ═══════════════════════════════════════════════════════════
       PARTNER: GET /api/partner/payouts
       🎯 የአጋሩ የ payout ታሪክ
       ═══════════════════════════════════════════════════════════ */
    public function partnerIndex(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized.',
                ], 401);
            }

            $payouts = Payout::where('user_id', $user->id)
                ->latest()
                ->get()
                ->map(function ($payout) {
                    return [
                        'id'        => $payout->id,
                        'reference' => $payout->reference 
                            ?? 'PO-' . str_pad($payout->id, 6, '0', STR_PAD_LEFT),
                        'date'      => $payout->created_at?->format('M d, Y') ?? '—',
                        'method'    => $this->formatMethod($payout->method),
                        'amount'    => number_format($payout->amount ?? 0),
                        'status'    => $this->formatStatus($payout->status),
                    ];
                });

            return response()->json([
                'success' => true,
                'data'    => $payouts,
            ]);

        } catch (\Exception $e) {
            Log::error('partnerIndex error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to load payouts.',
            ], 500);
        }
    }

   public function requestPayout(Request $request): JsonResponse
{
    try {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        // ── Validation ──
        $validator = Validator::make($request->all(), [
            'amount'          => 'required|numeric|min:100',
            'method'          => 'required|in:bank,telebirr,cbe_birr',
            'bank_account_id' => 'required_if:method,bank|exists:bank_accounts,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        // ── Wallet ──
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id],
            [
                'available_balance' => 0,
                'pending_balance'   => 0,
                'total_earned'      => 0,
                'total_withdrawn'   => 0,
            ]
        );

        $amount = (float) $request->amount;

        // ── ባላንስ ፍተሻ ──
        if ($amount > (float) $wallet->available_balance) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient balance. Available: ETB ' 
                    . number_format($wallet->available_balance),
            ], 422);
        }

        // ── Bank account ──
        $bankAccount = null;
        if ($request->method === 'bank') {
            $bankAccount = BankAccount::where('user_id', $user->id)
                ->where('id', $request->bank_account_id)
                ->first();

            if (!$bankAccount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bank account not found.',
                ], 404);
            }
        }

        DB::beginTransaction();

        // ── Payout ፍጠር — NOT NULL አምዶችን ይሙሉ ──
        $payout = Payout::create([
            'user_id'         => $user->id,
            'bank_account_id' => $bankAccount?->id,
            'transaction_id'  => 'TXN-' . strtoupper(Str::random(12)),
            'reference'       => 'PO-' . date('Y') . '-' . str_pad(
                (Payout::whereYear('created_at', date('Y'))->count() + 1),
                4, '0', STR_PAD_LEFT
            ),
            'amount'          => $amount,
            'fee'             => 0,
            'net_amount'      => $amount,
            'method'          => $request->method,
            'bank_name'       => $bankAccount?->bank_name,
            'account_number'  => $bankAccount?->account_number,
            'status'          => 'pending',
            'requested_at'    => now(),
        ]);

        // ── ከ available → pending ──
        $wallet->available_balance -= $amount;
        $wallet->pending_balance   += $amount;
        $wallet->save();

        // ── Transaction log ──
        Transaction::create([
            'user_id'       => $user->id,
            'description'   => "Payout request {$payout->reference}",
            'type'          => 'debit',
            'amount'        => $amount,
            'balance_after' => $wallet->available_balance,
            'reference'     => $payout->reference,
            'category'      => 'payout',
            'related_id'    => $payout->id,
        ]);

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Payout request submitted successfully!',
            'data'    => [
                'id'        => $payout->id,
                'reference' => $payout->reference,
                'amount'    => number_format($amount),
                'status'    => 'Pending',
            ],
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('requestPayout error: ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Failed to submit payout: ' . $e->getMessage(),
        ], 500);
    }
}

    /* ═══════════════════════════════════════════════════════════
       PARTNER: GET /api/partner/bank-accounts
       🎯 የአጋሩ የ bank accounts ዝርዝር
       ═══════════════════════════════════════════════════════════ */
    public function bankAccounts(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized.',
                ], 401);
            }

            $accounts = BankAccount::where('user_id', $user->id)
                ->orderBy('is_default', 'desc')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($account) {
                    return [
                        'id'            => $account->id,
                        'bankName'      => $account->bank_name,
                        'accountNumber' => $account->masked_account,
                        'accountHolder' => $account->account_holder,
                        'branch'        => $account->branch,
                        'isDefault'     => $account->is_default,
                        'status'        => $account->status,
                    ];
                });

            return response()->json([
                'success' => true,
                'data'    => $accounts,
            ]);

        } catch (\Exception $e) {
            Log::error('bankAccounts error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to load accounts.',
            ], 500);
        }
    }

    /* ═══════════════════════════════════════════════════════════
       PARTNER: POST /api/partner/bank-accounts
       🎯 አዲስ የ bank account መጨመር
       ═══════════════════════════════════════════════════════════ */
    public function addBankAccount(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized.',
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'bank_name'      => 'required|string|max:255',
                'account_number' => 'required|string|max:50',
                'account_holder' => 'required|string|max:255',
                'branch'         => 'nullable|string|max:255',
                'is_default'     => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                ], 422);
            }

            // ── Duplicate ፍተሻ ──
            $exists = BankAccount::where('user_id', $user->id)
                ->where('account_number', $request->account_number)
                ->where('bank_name', $request->bank_name)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'This bank account is already registered.',
                ], 422);
            }

            DB::beginTransaction();

            $isDefault = $request->boolean('is_default', false);
            $accountCount = BankAccount::where('user_id', $user->id)->count();

            // ── የመጀመሪያው ካሆነ auto default ──
            if ($accountCount === 0) {
                $isDefault = true;
            }

            // ── አዲሱ default ከሆነ ሌሎቹን unset ──
            if ($isDefault) {
                BankAccount::where('user_id', $user->id)
                    ->update(['is_default' => false]);
            }

            $account = BankAccount::create([
                'user_id'        => $user->id,
                'bank_name'      => $request->bank_name,
                'account_number' => $request->account_number,
                'account_holder' => $request->account_holder,
                'branch'         => $request->branch,
                'is_default'     => $isDefault,
                'status'         => 'active',
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Bank account added successfully!',
                'data'    => [
                    'id'            => $account->id,
                    'bankName'      => $account->bank_name,
                    'accountNumber' => $account->masked_account,
                    'accountHolder' => $account->account_holder,
                    'isDefault'     => $account->is_default,
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('addBankAccount error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to add account: ' . $e->getMessage(),
            ], 500);
        }
    }

    /* ═══════════════════════════════════════════════════════════
       PARTNER: DELETE /api/partner/bank-accounts/{id}
       🎯 የ bank account ማጥፋት
       ═══════════════════════════════════════════════════════════ */
    public function removeBankAccount(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized.',
                ], 401);
            }

            $account = BankAccount::where('user_id', $user->id)->find($id);

            if (! $account) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bank account not found.',
                ], 404);
            }

            DB::beginTransaction();

            $wasDefault = $account->is_default;
            $account->delete();

            // ── default ከነበረ ሌላ አካውንት default ያድርግ ──
            if ($wasDefault) {
                $nextAccount = BankAccount::where('user_id', $user->id)
                    ->orderBy('created_at', 'desc')
                    ->first();
                if ($nextAccount) {
                    $nextAccount->update(['is_default' => true]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Bank account removed.',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('removeBankAccount error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove account.',
            ], 500);
        }
    }

    /* ═══════════════════════════════════════════════════════════
       HELPERS
       ═══════════════════════════════════════════════════════════ */

    private function formatMethod(?string $method): string
    {
        return match ($method) {
            'bank'      => 'Bank Transfer',
            'telebirr'  => 'Telebirr',
            'cbe_birr'  => 'CBE Birr',
            default     => ucfirst($method ?? 'Bank'),
        };
    }

    private function formatStatus(?string $status): string
    {
        return match ($status) {
            'pending'  => 'Pending',
            'paid'     => 'Completed',
            'rejected' => 'Rejected',
            default    => ucfirst($status ?? 'Pending'),
        };
    }
}