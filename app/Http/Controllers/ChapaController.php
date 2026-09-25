<?php
// app/Http/Controllers/ChapaController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Booking;

class ChapaController extends Controller
{
    private $secretKey;
    private $publicKey;
    private $encryptionKey;
    private $baseUrl;
    
    public function __construct()
    {
        $this->secretKey = env('CHAPA_SECRET_KEY');
        $this->publicKey = env('CHAPA_PUBLIC_KEY');
        $this->encryptionKey = env('CHAPA_ENCRYPTION_KEY');
        $this->baseUrl = env('CHAPA_BASE_URL', 'https://api.chapa.co/v1');
    }
    
    // 1️⃣ Initialize Payment
    public function initialize(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'email' => 'required|email',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'phone_number' => 'required|string',
            'tx_ref' => 'required|string',
        ]);
        
        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/transaction/initialize", [
                'amount' => $validated['amount'],
                'currency' => 'ETB',
                'email' => $validated['email'],
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'phone_number' => $validated['phone_number'],
                'tx_ref' => $validated['tx_ref'],
                'callback_url' => route('chapa.callback'),
                'return_url' => route('chapa.return'),
                'customization' => [
                    'title' => 'Combolojo Booking',
                    'description' => 'Sports Venue Booking',
                ],
            ]);
        
        if ($response->successful()) {
            return response()->json([
                'status' => 'success',
                'data' => $response->json('data'),
            ]);
        }
        
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to initialize payment',
        ], 400);
    }
    
    // 2️⃣ Verify Payment
    public function verify($txRef)
    {
        $response = Http::withToken($this->secretKey)
            ->get("{$this->baseUrl}/transaction/verify/{$txRef}");
        
        if ($response->successful()) {
            return response()->json([
                'status' => 'success',
                'data' => $response->json('data'),
            ]);
        }
        
        return response()->json([
            'status' => 'failed',
        ], 400);
    }
    
    // 3️⃣ Webhook Callback
    public function callback(Request $request)
    {
        // ✅ Verify webhook signature
        $signature = $request->header('Chapa-Signature');
        $payload = $request->getContent();
        
        $expectedSignature = hash_hmac(
            'sha256',
            $payload,
            $this->encryptionKey
        );
        
        if ($signature !== $expectedSignature) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid signature',
            ], 401);
        }
        
        $txRef = $request->input('tx_ref');
        $status = $request->input('status');
        
        if ($status === 'success') {
            Booking::where('transaction_ref', $txRef)
                ->update(['payment_status' => 'paid']);
        }
        
        return response()->json(['status' => 'received']);
    }
}