<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ChapaController extends Controller
{
    protected string $baseUrl;
    protected string $secretKey;
    protected bool $demoMode;

    public function __construct()
    {
        $this->baseUrl   = config('services.chapa.base_url', 'https://api.chapa.co/v1');
        $this->secretKey = config('services.chapa.secret_key', '');
        $this->demoMode  = config('services.chapa.demo_mode', true);
    }

    // ============================================
    // 💳 INITIALIZE PAYMENT
    // POST /api/chapa/initialize
    // ============================================
    public function initialize(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount'       => 'required|numeric|min:1',
            'email'        => 'required|email',
            'first_name'   => 'required|string|max:100',
            'last_name'    => 'required|string|max:100',
            'phone_number' => 'nullable|string|max:20',
            'tx_ref'       => 'required|string|unique:bookings,transaction_ref',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $txRef = $request->tx_ref;

        // 🎭 DEMO MODE
        if ($this->demoMode) {
            $checkoutUrl = $this->getDemoCheckoutUrl(
                $txRef,
                $request->amount,
                $request->email,
                $request->first_name,
                $request->last_name
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'Demo checkout initialized',
                'data'    => [
                    'checkout_url' => $checkoutUrl,
                    'tx_ref'       => $txRef,
                    'is_demo'      => true,
                ],
            ], 200);
        }

        // 🚀 REAL CHAPA API
        try {
            $response = Http::withToken($this->secretKey)
                ->timeout(30)
                ->post("{$this->baseUrl}/transaction/initialize", [
                    'amount'       => (string) $request->amount,
                    'currency'     => 'ETB',
                    'email'        => $request->email,
                    'first_name'   => $request->first_name,
                    'last_name'    => $request->last_name,
                    'phone_number' => $request->phone_number,
                    'tx_ref'       => $txRef,
                    'callback_url' => config('services.chapa.callback_url'),
                    'return_url'   => config('services.chapa.return_url'),
                    'customization' => [
                        'title'       => 'Combolojo Booking',
                        'description' => 'Sports venue booking',
                    ],
                ]);

            $data = $response->json();

            if ($response->successful() && isset($data['data']['checkout_url'])) {
                return response()->json([
                    'status' => 'success',
                    'data'   => [
                        'checkout_url' => $data['data']['checkout_url'],
                        'tx_ref'       => $txRef,
                        'is_demo'      => false,
                    ],
                ], 200);
            }

            Log::error('Chapa init failed', ['response' => $data]);

            return response()->json([
                'status'  => 'error',
                'message' => $data['message'] ?? 'Failed to initialize Chapa payment',
            ], 400);

        } catch (\Exception $e) {
            Log::error('Chapa exception', ['error' => $e->getMessage()]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Payment gateway error: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ============================================
    // 🔍 VERIFY PAYMENT
    // GET /api/chapa/verify/{txRef}
    // ============================================
    public function verify(Request $request, string $txRef)
    {
        // 🎭 DEMO MODE
        if ($this->demoMode) {
            return response()->json([
                'status'  => 'success',
                'message' => 'Demo verification',
                'data'    => [
                    'tx_ref' => $txRef,
                    'status' => 'success',
                    'is_demo' => true,
                ],
            ], 200);
        }

        try {
            $response = Http::withToken($this->secretKey)
                ->timeout(15)
                ->get("{$this->baseUrl}/transaction/verify/{$txRef}");

            $data = $response->json();

            if ($response->successful() && isset($data['data'])) {
                return response()->json([
                    'status' => 'success',
                    'data'   => $data['data'],
                ], 200);
            }

            return response()->json([
                'status'  => 'error',
                'message' => $data['message'] ?? 'Verification failed',
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // ============================================
    // 🔔 CALLBACK (Chapa → Backend)
    // POST /api/chapa/callback
    // ============================================
    public function callback(Request $request)
    {
        Log::info('Chapa callback received', $request->all());

        $txRef = $request->input('tx_ref') ?? $request->input('trx_ref');
        $status = $request->input('status');

        if ($txRef && $status === 'success') {
            // Update booking status
            $booking = Booking::where('transaction_ref', $txRef)->first();
            if ($booking) {
                $booking->update([
                    'status'         => 'confirmed',
                    'payment_status' => 'paid',
                    'confirmed_at'   => now(),
                ]);
            }
        }

        return response()->json(['status' => 'success'], 200);
    }

    // ============================================
    // ↩️ RETURN (Chapa → User Browser)
    // GET /api/chapa/return
    // ============================================
    public function return(Request $request)
    {
        $txRef  = $request->query('tx_ref', '');
        $status = $request->query('status', 'success');

        // Return HTML page
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Complete</title>
    <style>
        body {
            font-family: -apple-system, sans-serif;
            background: #0F172A;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .card {
            background: white;
            color: #0F172A;
            padding: 40px;
            border-radius: 20px;
            text-align: center;
            max-width: 400px;
        }
        .icon { font-size: 60px; margin-bottom: 20px; }
        h1 { color: #10B981; margin: 10px 0; }
        p { color: #666; line-height: 1.6; }
        code {
            background: #F1F5F9;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 12px;
            display: inline-block;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">✅</div>
        <h1>Payment Successful!</h1>
        <p>Your booking has been confirmed.</p>
        <code>Ref: {$txRef}</code>
        <p style="margin-top: 30px; font-size: 12px; color: #999;">
            You can close this window now.
        </p>
    </div>
</body>
</html>
HTML;

        return response($html, 200)->header('Content-Type', 'text/html');
    }

    // ============================================
    // 🎭 DEMO CHECKOUT URL
    // ============================================
    protected function getDemoCheckoutUrl(
        string $txRef,
        float $amount,
        string $email,
        string $firstName,
        string $lastName
    ): string {
        $amountStr = number_format($amount, 2);
        $callbackUrl = config('services.chapa.callback_url');

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chapa Demo Checkout</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: -apple-system, sans-serif; }
        body {
            background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            max-width: 400px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .logo { text-align: center; font-size: 32px; font-weight: bold; color: #1A3E8F; margin-bottom: 10px; }
        .subtitle { text-align: center; color: #666; margin-bottom: 30px; font-size: 14px; }
        .amount { text-align: center; font-size: 42px; font-weight: bold; color: #0F172A; margin: 20px 0; }
        .currency { font-size: 20px; color: #666; }
        .info { background: #F8FAFC; border-radius: 12px; padding: 16px; margin: 20px 0; }
        .row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #E2E8F0; }
        .row:last-child { border-bottom: none; }
        .label { color: #666; font-size: 13px; }
        .value { color: #0F172A; font-weight: 600; font-size: 13px; }
        .btn {
            width: 100%;
            padding: 16px;
            border-radius: 12px;
            border: none;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 10px;
            transition: all 0.3s;
        }
        .btn-pay {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            color: white;
        }
        .btn-pay:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(16,185,129,0.4); }
        .btn-cancel { background: transparent; color: #666; margin-top: 15px; }
        .secure { text-align: center; color: #999; font-size: 12px; margin-top: 20px; }
        .demo-badge {
            background: #FEF3C7;
            color: #92400E;
            padding: 8px 16px;
            border-radius: 20px;
            display: inline-block;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="card">
        <div style="text-align: center;">
            <span class="demo-badge">DEMO MODE</span>
        </div>
        <div class="logo">Chapa</div>
        <div class="subtitle">Secure Payment Gateway</div>

        <div class="amount">
            <span class="currency">ETB</span> {$amountStr}
        </div>

        <div class="info">
            <div class="row">
                <span class="label">Email</span>
                <span class="value">{$email}</span>
            </div>
            <div class="row">
                <span class="label">Name</span>
                <span class="value">{$firstName} {$lastName}</span>
            </div>
            <div class="row">
                <span class="label">Transaction</span>
                <span class="value">{$txRef}</span>
            </div>
        </div>

        <button class="btn btn-pay" onclick="payNow()">Pay Now</button>
        <button class="btn btn-cancel" onclick="cancel()">Cancel</button>

        <div class="secure">🔒 Secured by Chapa</div>
    </div>

    <script>
        function payNow() {
    document.body.innerHTML = '...';
    setTimeout(function() {
        window.location.href = '{$callbackUrl}?tx_ref={$txRef}&status=success';
    }, 1500);
}

        function cancel() {
            window.location.href = '{$callbackUrl}?tx_ref={$txRef}&status=failed';
        }
    </script>
</body>
</html>
HTML;

        return 'data:text/html;charset=utf-8,' . rawurlencode($html);
    }
}